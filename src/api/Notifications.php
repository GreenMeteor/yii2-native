<?php

namespace greenmeteor\native\api;

use yii\base\InvalidArgumentException;

/** Local and remote (push) notifications. */
class Notifications extends BaseApi
{
    public static function apiName(): string
    {
        return 'Notifications';
    }

    /**
     * Schedule a local notification.
     *
     * @param int|null $at Unix timestamp; null delivers immediately
     */
    public function schedule(string $id, string $title, string $body, ?int $at = null, array $data = []): void
    {
        if ($id === '') {
            throw new InvalidArgumentException('Notification id cannot be empty.');
        }
        $this->invoke('schedule', $this->args([
            'id' => $id,
            'title' => $title,
            'body' => $body,
            'at' => $at,
            'data' => $data ?: null,
        ]));
    }

    public function cancel(string $id): void
    {
        $this->invoke('cancel', ['id' => $id]);
    }

    /**
     * Register for remote push. Result: `NativeEvents::PUSH_TOKEN_RECEIVED`.
     *
     * @return string request id
     */
    public function enrollPush(?string $id = null): string
    {
        $id = $this->requestId($id);
        $this->invoke('enrollPush', ['id' => $id]);

        return $id;
    }

    public function getPushToken(): ?string
    {
        $token = $this->invoke('getPushToken');

        return $token === null || $token === '' ? null : (string) $token;
    }

    public function setBadge(int $count): void
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Badge count cannot be negative.');
        }
        $this->invoke('setBadge', ['count' => $count]);
    }
}
