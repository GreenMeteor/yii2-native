<?php

namespace greenmeteor\native\tests\unit;

use greenmeteor\native\Bootstrap;
use greenmeteor\native\events\NativeEvent;
use greenmeteor\native\events\NativeEvents;
use greenmeteor\native\Native;
use greenmeteor\native\tests\Support\NativeTestCase;
use greenmeteor\native\tests\Support\TestRequest;
use greenmeteor\native\web\NativeResponseFormatter;
use greenmeteor\native\web\RendersNative;
use greenmeteor\native\widgets\Text;
use greenmeteor\native\widgets\VStack;
use yii\web\Application;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\MethodNotAllowedHttpException;

class WebBridgeTest extends NativeTestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
        parent::tearDown();
    }

    /** Boot a web app via the real Bootstrap. */
    private function web(array $native = [], bool $csrf = false): Native
    {
        if (\Yii::$app !== null) {
            \Yii::$app->getErrorHandler()->unregister();
        }
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['SCRIPT_FILENAME'] = __FILE__;

        new Application([
            'id' => 'web',
            'basePath' => dirname(__DIR__),
            'bootstrap' => [Bootstrap::class],
            'components' => [
                'request' => ['class' => TestRequest::class, 'cookieValidationKey' => 'k', 'enableCsrfValidation' => $csrf, 'scriptUrl' => '/index.php', 'scriptFile' => __FILE__, 'baseUrl' => ''],
                'urlManager' => ['enablePrettyUrl' => true, 'showScriptName' => false],
                'native' => array_merge(['class' => Native::class, 'appId' => 'com.example.web'], $native),
            ],
        ]);

        return \Yii::$app->get('native');
    }

    private function post(string $route, $body, array $headers = []): array
    {
        $request = \Yii::$app->getRequest();
        $request->body = is_string($body) ? $body : json_encode($body);
        foreach ($headers as $k => $v) {
            $request->getHeaders()->set($k, $v);
        }

        return \Yii::$app->runAction($route);
    }

    private function httpStatus(): int
    {
        return \Yii::$app->getResponse()->statusCode;
    }

    // ---- bootstrap ------------------------------------------------------

    public function testBootstrapRegistersRoutesControllerAndFormatter(): void
    {
        $this->web();
        $this->assertArrayHasKey('native-bridge', \Yii::$app->controllerMap);
        $this->assertSame(NativeResponseFormatter::class, \Yii::$app->getResponse()->formatters['native']);

        $rules = array_map(fn ($r) => $r->name ?? null, \Yii::$app->getUrlManager()->rules);
        $this->assertContains('_native/bridge', $rules);
        $this->assertContains('_native/events', $rules);
    }

    public function testBootstrapDoesNotOverrideAUserDefinedComponent(): void
    {
        $native = $this->web(['name' => 'Mine']);
        $this->assertSame('Mine', $native->name);
    }

    public function testConsoleBootstrapRegistersModuleAndNestedCommands(): void
    {
        $app = new \yii\console\Application([
            'id' => 'c', 'basePath' => dirname(__DIR__), 'bootstrap' => [Bootstrap::class],
            'components' => ['native' => ['class' => Native::class, 'appId' => 'com.example.cli']],
        ]);
        $this->assertTrue($app->hasModule('native'));

        $expect = [
            'native/doctor' => \greenmeteor\native\commands\DoctorController::class,
            'native/install' => \greenmeteor\native\commands\InstallController::class,
            'native/build' => \greenmeteor\native\commands\BuildController::class,
            'native/run' => \greenmeteor\native\commands\RunController::class,
            'native/manifest' => \greenmeteor\native\commands\ManifestController::class,
            'native/config' => \greenmeteor\native\commands\ConfigController::class,
            'native/plugin/list' => \greenmeteor\native\commands\PluginController::class, // the route the README documents
        ];
        foreach ($expect as $route => $class) {
            $result = $app->createController($route);
            $this->assertNotFalse($result, $route);
            $this->assertInstanceOf($class, $result[0], $route);
        }
        $this->assertSame('list', $app->createController('native/plugin/list')[1]);
    }

    // ---- /bridge --------------------------------------------------------

    public function testCallSuccessEnvelope(): void
    {
        $native = $this->web();
        $native->fake(['Device.getId' => 'dev-9']);
        $res = $this->post('native-bridge/call', ['method' => 'Device.getId']);
        $this->assertSame(['ok' => true, 'result' => 'dev-9'], $res);
        $this->assertSame(200, $this->httpStatus());
        $this->assertSame('json', \Yii::$app->getResponse()->format);
    }

    public function testArgumentsAreValidatedOnTheHttpPath(): void
    {
        $native = $this->web();
        $fake = $native->fake();
        $res = $this->post('native-bridge/call', ['method' => 'Browser.open', 'params' => ['url' => 'javascript:alert(1)']]);
        $this->assertSame(400, $this->httpStatus());
        $this->assertSame('invalid_argument', $res['error']['type']);
        $this->assertSame([], $fake->calls);
    }

    public function testUnknownAndDeniedFunctionsAreForbidden(): void
    {
        $native = $this->web(['http' => ['deny' => ['SecureStorage.*']]]);
        $fake = $native->fake();
        foreach (['Nope.nothing', 'SecureStorage.get'] as $fn) {
            $res = $this->post('native-bridge/call', ['method' => $fn, 'params' => ['key' => 'k']]);
            $this->assertSame(403, $this->httpStatus(), $fn);
            $this->assertSame('forbidden', $res['error']['type']);
        }
        $this->assertSame([], $fake->calls);
    }

    public function testMalformedBodiesAreRejected(): void
    {
        $this->web()->fake();
        foreach (['not json', '[]', '{"method":5}', '{"method":"Device.getId","params":"x"}'] as $body) {
            $res = $this->post('native-bridge/call', $body);
            $this->assertSame(400, $this->httpStatus(), $body);
            $this->assertSame('invalid_request', $res['error']['type']);
        }
    }

    public function testRuntimeIsRequiredByDefault(): void
    {
        $this->web();
        try {
            $this->post('native-bridge/call', ['method' => 'Device.getId']);
            $this->fail('Expected 503');
        } catch (HttpException $e) {
            $this->assertSame(503, $e->statusCode);
        }
    }

    public function testRuntimeRequirementCanBeLiftedForDevelopment(): void
    {
        $native = $this->web(['http' => ['requireRuntime' => false]]);
        $native->bridge->setTransport(new \greenmeteor\native\bridge\FakeTransport(['responses' => ['Device.getId' => 'x']]));
        $this->assertSame('x', $this->post('native-bridge/call', ['method' => 'Device.getId'])['result']);
    }

    public function testSharedSecret(): void
    {
        $secret = str_repeat('s3', 10);
        $native = $this->web(['http' => ['secret' => $secret]]);
        $native->fake(['Device.getId' => 'ok']);

        foreach ([[], ['X-Native-Secret' => 'wrong']] as $headers) {
            try {
                $this->post('native-bridge/call', ['method' => 'Device.getId'], $headers);
                $this->fail('Expected 403');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->statusCode);
            }
        }
        $this->assertSame('ok', $this->post('native-bridge/call', ['method' => 'Device.getId'], ['X-Native-Secret' => $secret])['result']);
    }

    public function testDisabledBridgeReturns404(): void
    {
        $this->web(['http' => ['enabled' => false]])->fake();
        try {
            $this->post('native-bridge/call', ['method' => 'Device.getId']);
            $this->fail('Expected 404');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->statusCode);
        }
    }

    public function testGetIsNotAllowed(): void
    {
        $this->web()->fake();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->expectException(MethodNotAllowedHttpException::class);
        \Yii::$app->runAction('native-bridge/call');
    }

    public function testCsrfValidationIsEnforced(): void
    {
        $this->web([], true)->fake(['Device.getId' => 'x']);
        $this->expectException(BadRequestHttpException::class);
        $this->post('native-bridge/call', ['method' => 'Device.getId']);
    }

    public function testPermissionAndNativeErrorsMapToStatusCodes(): void
    {
        $native = $this->web();
        $fake = $native->fake(['Dialog.toast' => new \greenmeteor\native\exceptions\NativeCallException('device said no')]);

        $res = $this->post('native-bridge/call', ['method' => 'Camera.getPhoto', 'params' => ['id' => 'a']]);
        $this->assertSame(403, $this->httpStatus());
        $this->assertSame('permission', $res['error']['type']);

        $e = \greenmeteor\native\exceptions\NativeCallException::fromPayload('Dialog.toast', ['type' => 'busy', 'message' => 'device said no']);
        $fake->responses['Dialog.toast'] = $e;
        $res = $this->post('native-bridge/call', ['method' => 'Dialog.toast', 'params' => ['message' => 'hi']]);
        $this->assertSame(502, $this->httpStatus());
        $this->assertSame('busy', $res['error']['type']);
    }

    public function testOversizedBodyIsRejected(): void
    {
        $this->web()->fake();
        try {
            $this->post('native-bridge/call', str_repeat('a', 1048577));
            $this->fail('Expected 413');
        } catch (HttpException $e) {
            $this->assertSame(413, $e->statusCode);
        }
    }

    // ---- /events --------------------------------------------------------

    public function testEventIsDispatchedToHandlers(): void
    {
        $native = $this->web();
        $native->fake();
        $got = null;
        $native->on(NativeEvents::ACTION, function (NativeEvent $e) use (&$got) {
            $got = [$e->type, $e->get('action'), $e->id];
        });

        $res = $this->post('native-bridge/event', ['event' => 'nativeAction', 'payload' => ['action' => 'go'], 'id' => 'r7']);
        $this->assertSame(['ok' => true, 'result' => null], $res);
        $this->assertSame(['nativeAction', 'go', 'r7'], $got);
    }

    public function testHandlerCanReplyWithAUiFrame(): void
    {
        $native = $this->web();
        $native->fake();
        $native->on(NativeEvents::ACTION, function (NativeEvent $e) {
            $e->response = VStack::make([], [Text::make(['text' => 'Next screen'])]);
        });

        $res = $this->post('native-bridge/event', ['event' => 'nativeAction', 'payload' => ['action' => 'next']]);
        $this->assertSame(1, $res['result']['version']);
        $this->assertSame('Next screen', $res['result']['root']['children'][0]['properties']['text']);
        $this->assertSame('root.0', $res['result']['root']['children'][0]['properties']['id']);
    }

    public function testUnknownEventsAreRejectedUnlessAllowed(): void
    {
        $native = $this->web();
        $native->fake();
        $res = $this->post('native-bridge/event', ['event' => 'madeUp', 'payload' => []]);
        $this->assertSame(403, $this->httpStatus());
        $this->assertSame('forbidden', $res['error']['type']);

        $native = $this->web(['http' => ['allowUnknownEvents' => true]]);
        $native->fake();
        $this->assertTrue($this->post('native-bridge/event', ['event' => 'madeUp', 'payload' => []])['ok']);
    }

    public function testEventNamesAreValidated(): void
    {
        $this->web(['http' => ['allowUnknownEvents' => true]])->fake();
        foreach (['', 'has space', "x\n", str_repeat('a', 200), '1abc'] as $name) {
            $res = $this->post('native-bridge/event', ['event' => $name, 'payload' => []]);
            $this->assertSame(400, $this->httpStatus(), json_encode($name));
            $this->assertFalse($res['ok']);
        }
    }

    // ---- response format ------------------------------------------------

    public function testNativeResponseFormatAndTrait(): void
    {
        $this->web();
        $controller = new class('t', \Yii::$app) extends \yii\web\Controller {
            use RendersNative;
        };
        $response = $controller->asNative(VStack::make([], [Text::make(['text' => 'Hi'])]));
        (new NativeResponseFormatter())->format($response);

        $frame = json_decode($response->content, true);
        $this->assertSame('vstack', $frame['root']['type']);
        $this->assertSame('root.0', $frame['root']['children'][0]['properties']['id']);
        $this->assertStringStartsWith('application/json', $response->getHeaders()->get('Content-Type'));
    }

    public function testFormatterRejectsNonWidgets(): void
    {
        $this->web();
        $response = \Yii::$app->getResponse();
        $response->format = 'native';
        $response->data = ['not' => 'a widget'];
        $this->expectException(\yii\base\InvalidArgumentException::class);
        (new NativeResponseFormatter())->format($response);
    }
}
