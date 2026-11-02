<?php

namespace greenmeteor\native\events;

use yii\base\Event;

/**
 * An event delivered from the native shell to PHP
 * (lifecycle, UI actions, completion of interactive calls, ...).
 *
 * Note: Yii overwrites {@see Event::$name} on every trigger, so use
 * {@see $type} when you need the original native event name.
 */
class NativeEvent extends Event
{
    /** Native event name, e.g. {@see NativeEvents::CAMERA_PHOTO_TAKEN}. */
    public string $type = '';
    /** Event payload as sent by the native shell. */
    public array $payload = [];
    /** Request id correlating the event with an interactive call, if any. */
    public ?string $id = null;
    /**
     * Optional reply. If a handler sets this to a Widget/Element, the HTTP
     * events endpoint returns it to the shell as a rendered UI frame.
     */
    public $response;
    /**
     * For deep-link events: the resolved `['route' => ..., 'params' => [...]]`,
     * or null when the URL is not allowed or matches no rule.
     */
    public ?array $route = null;

    public function get(string $key, $default = null)
    {
        return $this->payload[$key] ?? $default;
    }
}
