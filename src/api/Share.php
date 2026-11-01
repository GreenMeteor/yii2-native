<?php

namespace greenmeteor\native\api;

class Share extends BaseApi
{
    public static function apiName(): string
    {
        return 'Share';
    }

    public function text(string $text, ?string $title = null, ?string $url = null): void
    {
        if ($url !== null) {
            $this->assertHttpUrl($url);
        }
        $this->invoke('text', $this->args(['title' => $title, 'text' => $text, 'url' => $url]));
    }

    public function url(string $url, ?string $title = null): void
    {
        $this->assertHttpUrl($url);
        $this->invoke('url', $this->args(['title' => $title, 'url' => $url]));
    }

    public function file(string $path, ?string $title = null, ?string $mime = null): void
    {
        $this->assertPath($path);
        $this->invoke('file', $this->args(['title' => $title, 'path' => $path, 'mime' => $mime]));
    }
}
