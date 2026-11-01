<?php

namespace greenmeteor\native\api;

class Microphone extends BaseApi
{
    public static function apiName(): string
    {
        return 'Microphone';
    }

    /**
     * Start recording. Result when finished: `NativeEvents::MICROPHONE_RECORDED`.
     *
     * @return string request id
     */
    public function start(?int $maxDuration = null, ?string $id = null): string
    {
        $id = $this->requestId($id);
        $this->invoke('start', $this->args(['maxDuration' => $maxDuration, 'id' => $id]));

        return $id;
    }

    public function stop(): void
    {
        $this->invoke('stop');
    }

    public function pause(): void
    {
        $this->invoke('pause');
    }

    public function resume(): void
    {
        $this->invoke('resume');
    }

    /** @return string idle|recording|paused */
    public function getStatus(): string
    {
        return (string) ($this->invoke('getStatus') ?? 'idle');
    }
}
