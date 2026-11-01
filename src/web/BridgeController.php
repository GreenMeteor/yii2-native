<?php

namespace greenmeteor\native\web;

use greenmeteor\native\exceptions\NativeCallException;
use greenmeteor\native\exceptions\NativePermissionException;
use greenmeteor\native\exceptions\NativeUnavailableException;
use greenmeteor\native\Native;
use greenmeteor\native\renderer\Element;
use greenmeteor\native\widgets\Widget;
use Yii;
use yii\base\InvalidArgumentException;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Server side of `resources/js/native.js`.
 *
 * - `POST /_native/bridge` `{method, params}`: call a bridge function
 * - `POST /_native/events` `{event, payload, id?}`: deliver a native event
 *
 * Responses use an envelope: `{"ok":true,"result":...}` or
 * `{"ok":false,"error":{"type":"...","message":"..."}}`.
 *
 * Hardening: POST only, Yii CSRF validation, optional shared secret
 * (`X-Native-Secret`), runtime must be present, allow/deny function patterns,
 * request body size cap, and only known functions/events by default.
 */
class BridgeController extends Controller
{
    private const MAX_BODY_BYTES = 1048576;

    public $enableCsrfValidation = true;

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['call' => ['POST'], 'event' => ['POST']],
            ],
        ];
    }

    public function beforeAction($action): bool
    {
        Yii::$app->getResponse()->format = Response::FORMAT_JSON;
        $native = $this->native();

        if (!$native->http['enabled']) {
            throw new HttpException(404, 'The native HTTP bridge is disabled.');
        }

        $secret = $native->http['secret'];
        if ($secret !== null) {
            $given = (string) Yii::$app->getRequest()->getHeaders()->get('X-Native-Secret', '');
            if (!hash_equals((string) $secret, $given)) {
                throw new HttpException(403, 'Invalid native secret.');
            }
        }

        if ($native->http['requireRuntime'] && !$native->isAvailable()) {
            throw new HttpException(503, 'The native runtime is not available.');
        }

        return parent::beforeAction($action);
    }

    public function actionCall(): array
    {
        $body = $this->readBody();
        $method = $body['method'] ?? null;
        $params = $body['params'] ?? [];

        if (!is_string($method) || !is_array($params)) {
            return $this->fail(400, 'invalid_request', "Body must be {method: string, params: object}.");
        }

        $native = $this->native();
        if (!$native->isHttpFunctionAllowed($method)) {
            return $this->fail(403, 'forbidden', "Native function '{$method}' is not callable over HTTP.");
        }

        try {
            return ['ok' => true, 'result' => $native->call($method, $params)];
        } catch (NativeUnavailableException $e) {
            return $this->fail(503, 'unavailable', $e->getMessage());
        } catch (NativePermissionException $e) {
            return $this->fail(403, 'permission', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->fail(400, 'invalid_argument', $e->getMessage());
        } catch (NativeCallException $e) {
            return $this->fail(502, $e->errorType, $e->getMessage());
        }
    }

    public function actionEvent(): array
    {
        $body = $this->readBody();
        $type = $body['event'] ?? null;
        $payload = $body['payload'] ?? [];

        if (!is_string($type) || !preg_match('/^[A-Za-z][A-Za-z0-9._:\-]{0,127}\z/', $type) || !is_array($payload)) {
            return $this->fail(400, 'invalid_request', "Body must be {event: string, payload: object}.");
        }

        $native = $this->native();
        if (!$native->http['allowUnknownEvents'] && !$native->isKnownEvent($type)) {
            return $this->fail(403, 'forbidden', "Unknown native event '{$type}'.");
        }

        $id = isset($body['id']) && is_scalar($body['id']) ? (string) $body['id'] : null;
        $event = $native->dispatch($type, $payload, $id);

        $result = null;
        if ($event->response instanceof Widget || $event->response instanceof Element) {
            $result = $native->renderer->render($event->response);
        } elseif (is_array($event->response)) {
            $result = $event->response;
        }

        return ['ok' => true, 'result' => $result];
    }

    private function native(): Native
    {
        return Yii::$app->get('native');
    }

    private function readBody(): array
    {
        $raw = Yii::$app->getRequest()->getRawBody();
        if (strlen($raw) > self::MAX_BODY_BYTES) {
            throw new HttpException(413, 'Request body too large.');
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    private function fail(int $status, string $type, string $message): array
    {
        Yii::$app->getResponse()->setStatusCode($status);

        return ['ok' => false, 'error' => ['type' => $type, 'message' => $message]];
    }
}
