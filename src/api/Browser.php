<?php

namespace greenmeteor\native\api;

/** System / in-app browser. Only absolute http(s) URLs are accepted. */
class Browser extends BaseApi
{
    public static function apiName(): string
    {
        return 'Browser';
    }

    public function open(string $url): void
    {
        $this->assertHttpUrl($url);
        $this->invoke('open', ['url' => $url]);
    }

    public function openInApp(string $url): void
    {
        $this->assertHttpUrl($url);
        $this->invoke('openInApp', ['url' => $url]);
    }

    /**
     * Web authentication session (OAuth). Result: `NativeEvents::BROWSER_AUTH_COMPLETED`.
     *
     * @return string request id
     */
    public function authenticate(string $url, ?string $callbackScheme = null, ?string $id = null): string
    {
        $this->assertHttpUrl($url);
        $id = $this->requestId($id);
        $this->invoke('authenticate', $this->args(['url' => $url, 'callbackScheme' => $callbackScheme, 'id' => $id]));

        return $id;
    }
}
