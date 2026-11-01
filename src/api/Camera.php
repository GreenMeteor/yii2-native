<?php

namespace greenmeteor\native\api;

/**
 * Camera and photo picker. Results arrive as
 * `NativeEvents::CAMERA_PHOTO_TAKEN`, `MEDIA_PICKED`, `CAMERA_VIDEO_RECORDED`.
 */
class Camera extends BaseApi
{
    public static function apiName(): string
    {
        return 'Camera';
    }

    /** @return string request id */
    public function getPhoto(?string $id = null): string
    {
        $id = $this->requestId($id);
        $this->invoke('getPhoto', ['id' => $id]);

        return $id;
    }

    /** @return string request id */
    public function pickImages(bool $multiple = false, ?int $maxItems = null, ?string $id = null): string
    {
        $id = $this->requestId($id);
        $this->invoke('pickImages', $this->args(['multiple' => $multiple, 'maxItems' => $maxItems, 'id' => $id]));

        return $id;
    }

    /** @return string request id */
    public function recordVideo(?int $maxDuration = null, ?string $id = null): string
    {
        $id = $this->requestId($id);
        $this->invoke('recordVideo', $this->args(['maxDuration' => $maxDuration, 'id' => $id]));

        return $id;
    }
}
