<?php

namespace greenmeteor\native\routing;

/**
 * Turns an incoming deep link into a Yii route.
 *
 * Accepted URLs are strictly limited to the configured custom scheme
 * (`acme://orders/42`) and to `https` / `http` URLs whose host is in
 * `deepLinkHosts`. Everything else resolves to null.
 *
 * Rules map a path pattern to a route:
 *
 * ```php
 * 'deepLinks' => [
 *     'orders/<id:\d+>' => 'orders/view',
 *     'promo/<code>'    => 'promo/open',
 *     ''                => 'site/index',
 * ],
 * ```
 *
 * With these rules `acme://orders/42` resolves to the route `orders/view` with the
 * params `['id' => '42']`. A segment without a regex defaults to `[^/]+`.
 *
 * Query parameters are merged into `params`; values captured from the path win.
 */
class DeepLinkResolver
{
    private const MAX_URL_LENGTH = 2048;

    /** @var array<int,array{regex:string,names:string[],route:string}> */
    private array $compiled = [];

    /**
     * @param string[] $hosts
     * @param array<string,string> $rules pattern => route
     */
    public function __construct(
        private readonly ?string $scheme,
        private readonly array $hosts,
        array $rules
    ) {
        foreach ($rules as $pattern => $route) {
            $this->compiled[] = $this->compile((string) $pattern, (string) $route);
        }
    }

    /** @return array{route:string,params:array}|null */
    public function resolve(string $url): ?array
    {
        if ($url === '' || strlen($url) > self::MAX_URL_LENGTH || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);

        if ($this->scheme !== null && $scheme === strtolower($this->scheme)) {
            $path = ($parts['host'] ?? '') . ($parts['path'] ?? '');
        } elseif (in_array($scheme, ['http', 'https'], true)
            && isset($parts['host'])
            && in_array(strtolower($parts['host']), array_map('strtolower', $this->hosts), true)
        ) {
            $path = $parts['path'] ?? '';
        } else {
            return null;
        }

        $path = trim($path, '/');
        $segments = $path === '' ? [] : explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        foreach ($this->compiled as $rule) {
            if (preg_match($rule['regex'], $path, $m) === 1) {
                $params = $query;
                foreach ($rule['names'] as $name) {
                    $params[$name] = rawurldecode($m[$name]);
                }

                return ['route' => $rule['route'], 'params' => $params];
            }
        }

        return null;
    }

    private function compile(string $pattern, string $route): array
    {
        $names = [];
        $regex = '';
        $offset = 0;
        $pattern = trim($pattern, '/');

        preg_match_all('/<(\w+)(?::([^>]+))?>/', $pattern, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($matches as $m) {
            $regex .= preg_quote(substr($pattern, $offset, $m[0][1] - $offset), '#');
            $names[] = $m[1][0];
            $regex .= '(?P<' . $m[1][0] . '>' . ($m[2][0] ?? '[^/]+') . ')';
            $offset = $m[0][1] + strlen($m[0][0]);
        }
        $regex .= preg_quote(substr($pattern, $offset), '#');

        return ['regex' => '#^' . $regex . '\z#u', 'names' => $names, 'route' => $route];
    }
}
