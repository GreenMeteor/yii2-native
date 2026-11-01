<?php

namespace greenmeteor\native\web;

use greenmeteor\native\renderer\Element;
use greenmeteor\native\widgets\Widget;
use Yii;
use yii\base\Component;
use yii\base\InvalidArgumentException;
use yii\web\Response;
use yii\web\ResponseFormatterInterface;

/**
 * Formats a Widget/Element as a protocol frame. Registered by the bootstrap
 * as the `native` response format.
 */
class NativeResponseFormatter extends Component implements ResponseFormatterInterface
{
    public const FORMAT = 'native';

    public function format($response): void
    {
        $data = $response->data;
        if (!$data instanceof Widget && !$data instanceof Element) {
            throw new InvalidArgumentException('The native response format requires a Widget or Element.');
        }

        $response->getHeaders()->set('Content-Type', 'application/json; charset=UTF-8');
        $response->content = Yii::$app->get('native')->renderer->encode($data);
    }
}
