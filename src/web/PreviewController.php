<?php

namespace greenmeteor\native\web;

use greenmeteor\native\events\NativeEvents;
use greenmeteor\native\Native;
use greenmeteor\native\renderer\Element;
use greenmeteor\native\widgets\Widget;
use Yii;
use yii\filters\VerbFilter;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Development preview: render a screen in a browser, click actions, and see
 * the bridge calls your handlers make. **Disabled unless `preview.enabled`.**
 *
 * - `GET  /_native/preview?screen=/site/screen`  the preview page
 * - `GET  /_native/preview.js`                   the renderer module
 * - `POST /_native/preview/event`                deliver a `nativeAction`
 *
 * With `preview.fake` (default) every event runs against a {@see \greenmeteor\native\bridge\FakeTransport},
 * so device calls made by handlers are recorded and answered with
 * `preview.responses` instead of reaching a device.
 */
class PreviewController extends Controller
{
    public $enableCsrfValidation = true;

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['index' => ['GET'], 'script' => ['GET'], 'event' => ['POST']],
            ],
        ];
    }

    public function beforeAction($action): bool
    {
        if (empty($this->native()->preview['enabled'])) {
            throw new HttpException(404, 'Page not found.');
        }

        return parent::beforeAction($action);
    }

    public function actionIndex(): string
    {
        $response = Yii::$app->getResponse();
        $response->format = Response::FORMAT_HTML;
        $response->getHeaders()->set('X-Content-Type-Options', 'nosniff');
        $response->getHeaders()->set('Cache-Control', 'no-store');

        $config = Json::htmlEncode([
            'screen' => (string) Yii::$app->getRequest()->get('screen', ''),
            'events' => Url::to(['/native-preview/event']),
            'script' => Url::to(['/native-preview/script']),
            'csrf' => Yii::$app->getRequest()->getCsrfToken(),
        ]);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Yii2 Native preview</title>
<style>
  body{font:14px system-ui,sans-serif;margin:0;display:flex;gap:24px;padding:24px;background:#eef0f3;color:#1c1e21}
  .phone{width:375px;min-height:667px;background:#fff;border-radius:28px;box-shadow:0 8px 30px rgba(0,0,0,.2);padding:12px;box-sizing:border-box;overflow:auto}
  .side{flex:1;max-width:520px}
  .side input{width:70%;padding:6px}.side button{padding:6px 10px}
  #status{margin:8px 0;color:#555}
  #calls{font:12px ui-monospace,monospace;padding-left:18px}
  .nv-button{padding:8px 14px;border-radius:8px;border:1px solid #888;background:#fff;cursor:pointer}
  .nv-filled{background:#2563eb;color:#fff;border-color:#2563eb}.nv-text{border-color:transparent;background:transparent}
  .nv-card{padding:12px;background:#fff;border-radius:10px}
  .nv-topbar{display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid #ddd}.nv-title{flex:1;font-weight:600}
  .nv-bottomnav{display:flex;justify-content:space-around;border-top:1px solid #ddd;padding-top:6px}.nv-active{font-weight:700}
  .nv-fab{position:sticky;bottom:12px;margin-left:auto;display:block;border-radius:28px;padding:14px 18px;border:0;background:#2563eb;color:#fff}
  .nv-zstack>*{grid-area:1/1}.nv-unknown{color:#b91c1c;font:12px ui-monospace,monospace}
</style>
</head>
<body>
<div class="phone" id="phone" aria-label="Preview"></div>
<div class="side">
  <h3>Yii2 Native preview</h3>
  <p>Development tool. It approximates native rendering; it is not a substitute for a device.</p>
  <form id="load"><input id="screen" placeholder="/site/screen (a URL returning a frame)"> <button>Load</button></form>
  <div id="status"></div>
  <h4>Bridge calls</h4>
  <ol id="calls" reversed></ol>
</div>
<script type="module">
const cfg = {$config};
const m = await import(cfg.script);
const app = m.mountPreview({
  screen: cfg.screen, events: cfg.events, csrf: cfg.csrf,
  phone: document.getElementById('phone'), callsLog: document.getElementById('calls'), status: document.getElementById('status'),
});
const input = document.getElementById('screen');
input.value = cfg.screen;
document.getElementById('load').addEventListener('submit', (e) => { e.preventDefault(); if (input.value) app.load(input.value); });
if (cfg.screen) app.load(cfg.screen);
</script>
</body>
</html>
HTML;
    }

    public function actionScript(): string
    {
        $response = Yii::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->getHeaders()->set('Content-Type', 'text/javascript; charset=UTF-8');
        $response->getHeaders()->set('X-Content-Type-Options', 'nosniff');

        return (string) file_get_contents(dirname(__DIR__, 2) . '/resources/preview/preview.js');
    }

    public function actionEvent(): array
    {
        Yii::$app->getResponse()->format = Response::FORMAT_JSON;
        $native = $this->native();

        $raw = Yii::$app->getRequest()->getRawBody();
        $body = strlen($raw) <= 1048576 ? json_decode($raw, true) : null;
        $type = is_array($body) ? ($body['event'] ?? null) : null;
        $payload = is_array($body) ? ($body['payload'] ?? []) : [];

        if ($type !== NativeEvents::ACTION || !is_array($payload)) {
            Yii::$app->getResponse()->setStatusCode(400);

            return ['ok' => false, 'error' => ['type' => 'invalid_request', 'message' => 'The preview delivers nativeAction events only.']];
        }

        $fake = !empty($native->preview['fake']) ? $native->fake((array) $native->preview['responses']) : null;
        $event = $native->dispatch($type, $payload);

        $result = null;
        if ($event->response instanceof Widget || $event->response instanceof Element) {
            $result = $native->renderer->render($event->response);
        }

        $calls = [];
        foreach ($fake?->calls ?? [] as $call) {
            $entry = ['function' => $call['function']];
            if (!str_starts_with($call['function'], 'SecureStorage.')) {
                $entry['arguments'] = $call['arguments'];
            }
            $calls[] = $entry;
        }

        return ['ok' => true, 'result' => $result, 'calls' => $calls];
    }

    private function native(): Native
    {
        return Yii::$app->get('native');
    }
}
