<?php

namespace greenmeteor\native\web;

use greenmeteor\native\renderer\Element;
use greenmeteor\native\widgets\Widget;
use Yii;
use yii\web\Response;

/**
 * Controller helper to return a native UI frame from an action.
 *
 * ```php
 * class HomeController extends Controller
 * {
 *     use RendersNative;
 *
 *     public function actionIndex()
 *     {
 *         return $this->asNative(VStack::make([], [Text::make(['text' => 'Hi'])]));
 *     }
 * }
 * ```
 */
trait RendersNative
{
    public function asNative(Widget|Element $root): Response
    {
        $response = Yii::$app->getResponse();
        $response->format = NativeResponseFormatter::FORMAT;
        $response->data = $root;

        return $response;
    }
}
