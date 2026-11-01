<?php

namespace greenmeteor\native\api;

use yii\base\InvalidArgumentException;

class Dialog extends BaseApi
{
    public static function apiName(): string
    {
        return 'Dialog';
    }

    /**
     * Native alert. Result: `NativeEvents::DIALOG_BUTTON_PRESSED` (`index`, `label`).
     *
     * @param string[] $buttons
     * @return string request id
     */
    public function alert(string $title, string $message, array $buttons = ['OK'], ?string $id = null): string
    {
        if ($buttons === [] || count($buttons) > 3) {
            throw new InvalidArgumentException('An alert needs between 1 and 3 buttons.');
        }
        $id = $this->requestId($id);
        $this->invoke('alert', [
            'title' => $title,
            'message' => $message,
            'buttons' => array_values(array_map('strval', $buttons)),
            'id' => $id,
        ]);

        return $id;
    }

    /** @param string $duration `short` or `long` */
    public function toast(string $message, string $duration = 'short'): void
    {
        $this->assertOneOf($duration, ['short', 'long'], 'toast duration');
        $this->invoke('toast', ['message' => $message, 'duration' => $duration]);
    }
}
