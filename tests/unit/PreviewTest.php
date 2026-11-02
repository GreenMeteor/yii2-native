<?php

namespace greenmeteor\native\tests\unit;

use greenmeteor\native\Bootstrap;
use greenmeteor\native\build\Doctor;
use greenmeteor\native\events\NativeEvent;
use greenmeteor\native\events\NativeEvents;
use greenmeteor\native\Native;
use greenmeteor\native\tests\Support\NativeTestCase;
use greenmeteor\native\tests\Support\TestRequest;
use greenmeteor\native\widgets\Text;
use greenmeteor\native\widgets\VStack;
use yii\web\Application;
use yii\web\HttpException;
use yii\web\MethodNotAllowedHttpException;

class PreviewTest extends NativeTestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
        parent::tearDown();
    }

    private function web(array $native = [], string $method = 'GET', array $get = []): Native
    {
        if (\Yii::$app !== null) {
            \Yii::$app->getErrorHandler()->unregister();
        }
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['SCRIPT_FILENAME'] = __FILE__;
        $_GET = $get;

        new Application([
            'id' => 'web',
            'basePath' => dirname(__DIR__),
            'bootstrap' => [Bootstrap::class],
            'components' => [
                'request' => ['class' => TestRequest::class, 'cookieValidationKey' => 'k', 'enableCsrfValidation' => false, 'scriptUrl' => '/index.php', 'scriptFile' => __FILE__, 'baseUrl' => ''],
                'urlManager' => ['enablePrettyUrl' => true, 'showScriptName' => false],
                'native' => array_merge(['class' => Native::class, 'appId' => 'com.example.web', 'http' => ['secret' => 'top-secret-value-1234']], $native),
            ],
        ]);

        return \Yii::$app->get('native');
    }

    private function postEvent($body): array
    {
        \Yii::$app->getRequest()->body = is_string($body) ? $body : json_encode($body);

        return \Yii::$app->runAction('native-preview/event');
    }

    public function testDisabledByDefaultEveryActionIs404(): void
    {
        foreach (['index' => 'GET', 'script' => 'GET', 'event' => 'POST'] as $action => $method) {
            $this->web([], $method);
            try {
                \Yii::$app->runAction("native-preview/{$action}");
                $this->fail("{$action} was reachable while disabled");
            } catch (HttpException $e) {
                $this->assertSame(404, $e->statusCode, $action);
            }
        }
    }

    public function testRoutesAreRegistered(): void
    {
        $this->web();
        $this->assertArrayHasKey('native-preview', \Yii::$app->controllerMap);
        $names = array_map(fn ($r) => $r->name ?? null, \Yii::$app->getUrlManager()->rules);
        foreach (['_native/preview', '_native/preview.js', '_native/preview/event'] as $rule) {
            $this->assertContains($rule, $names);
        }
    }

    public function testIndexPageEscapesTheScreenParameterAndNeverLeaksTheSecret(): void
    {
        $evil = '</script><script>alert(1)</script>';
        $this->web(['preview' => ['enabled' => true]], 'GET', ['screen' => $evil]);
        $html = \Yii::$app->runAction('native-preview/index');

        $this->assertStringNotContainsString($evil, $html, 'raw script-closing sequence must not appear');
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertSame(1, substr_count($html, '<script'), 'only the page\'s own module script');
        $this->assertStringNotContainsString('top-secret-value-1234', $html);
        $this->assertSame(1, preg_match('/const cfg = (\{.*?\});\n/s', $html, $m));
        $cfg = json_decode($m[1], true);
        $this->assertSame($evil, $cfg['screen'], 'the value round-trips intact, just safely encoded');
        $this->assertSame('/_native/preview.js', $cfg['script']);
        $this->assertSame('/_native/preview/event', $cfg['events']);
        $this->assertArrayNotHasKey('secret', $cfg);
        $this->assertSame('html', \Yii::$app->getResponse()->format);
        $this->assertSame('no-store', \Yii::$app->getResponse()->getHeaders()->get('Cache-Control'));
    }

    public function testScriptIsServedAsJavaScript(): void
    {
        $this->web(['preview' => ['enabled' => true]]);
        $js = \Yii::$app->runAction('native-preview/script');
        $this->assertStringContainsString('export function renderFrame', $js);
        $this->assertStringContainsString('text/javascript', \Yii::$app->getResponse()->getHeaders()->get('Content-Type'));
        $this->assertSame('raw', \Yii::$app->getResponse()->format);
    }

    public function testWrongMethodIsRejected(): void
    {
        $this->web(['preview' => ['enabled' => true]], 'GET');
        $this->expectException(MethodNotAllowedHttpException::class);
        \Yii::$app->runAction('native-preview/event');
    }

    public function testActionRunsHandlersAgainstTheFakeAndReturnsFrameAndCalls(): void
    {
        $native = $this->web(['preview' => ['enabled' => true, 'responses' => ['Device.getId' => 'preview-device'], 'fake' => true], 'permissions' => []], 'POST');
        $seen = null;
        $native->on(NativeEvents::ACTION, function (NativeEvent $e) use ($native, &$seen) {
            $seen = $native->device->getId();
            $native->haptics->impact('light');
            $native->secureStorage->set('token', 'SUPER-SECRET');
            $e->response = VStack::make([], [Text::make(['text' => 'Done'])]);
        });

        $res = $this->postEvent(['event' => 'nativeAction', 'payload' => ['action' => 'go']]);

        $this->assertTrue($res['ok']);
        $this->assertSame('preview-device', $seen, 'fake responses are used');
        $this->assertSame('Done', $res['result']['root']['children'][0]['properties']['text']);
        $this->assertSame('root.0', $res['result']['root']['children'][0]['properties']['id']);
        $this->assertSame(['Device.getId', 'Haptics.impact', 'SecureStorage.set'], array_column($res['calls'], 'function'));
        $this->assertSame(['style' => 'light'], $res['calls'][1]['arguments']);
        $this->assertArrayNotHasKey('arguments', $res['calls'][2], 'secure storage arguments are never echoed');
        $this->assertStringNotContainsString('SUPER-SECRET', json_encode($res));
    }

    public function testOnlyNativeActionEventsAreAccepted(): void
    {
        $this->web(['preview' => ['enabled' => true]], 'POST');
        $fired = false;
        \Yii::$app->get('native')->on(NativeEvents::DEEP_LINK, function () use (&$fired) { $fired = true; });

        foreach ([
            ['event' => 'nativeDeepLink', 'payload' => ['url' => 'x']],
            ['event' => 'nativeAction', 'payload' => 'str'],
            ['event' => 5],
            'not json',
        ] as $body) {
            $res = $this->postEvent($body);
            $this->assertFalse($res['ok'], json_encode($body));
            $this->assertSame(400, \Yii::$app->getResponse()->statusCode);
        }
        $this->assertFalse($fired);
    }

    public function testFakeCanBeDisabledSoRealBridgeRules(): void
    {
        $native = $this->web(['preview' => ['enabled' => true, 'fake' => false]], 'POST');
        $native->on(NativeEvents::ACTION, function () use ($native) {
            $native->device->getId();
        });
        $this->expectException(\greenmeteor\native\exceptions\NativeUnavailableException::class);
        $this->postEvent(['event' => 'nativeAction', 'payload' => ['action' => 'x']]);
    }

    public function testDoctorFlagsPreviewInProduction(): void
    {
        $find = fn (array $c) => array_values(array_filter($c, fn ($x) => $x['name'] === 'Preview'))[0];
        $this->assertSame(Doctor::OK, $find((new Doctor($this->native()))->run())['status']);
        $this->assertSame(Doctor::WARN, $find((new Doctor($this->native(['preview' => ['enabled' => true]])))->run())['status']);
        $this->assertFalse(defined('YII_ENV_PROD') && YII_ENV_PROD);
    }
}
