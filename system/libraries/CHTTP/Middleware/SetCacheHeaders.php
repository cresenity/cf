<?php

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sets Cache-Control, ETag and Last-Modified on cacheable successful responses, e.g.
 * `CHTTP_Middleware_SetCacheHeaders::using(['max_age' => 300, 'etag' => true, 'public'])`.
 */
class CHTTP_Middleware_SetCacheHeaders {
    /**
     * Build the middleware string (`Class:option;option=value`) for the given options.
     *
     * @param array|string $options
     *
     * @return string
     */
    public static function using($options) {
        if (is_string($options)) {
            return static::class . ':' . $options;
        }

        $parts = [];
        foreach ($options as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $parts[] = $key;
                }

                continue;
            }

            $parts[] = is_int($key) ? $value : $key . '=' . $value;
        }

        return static::class . ':' . implode(';', $parts);
    }

    /**
     * @param CHTTP_Request $request
     * @param Closure       $next
     * @param array|string  $options
     *
     * @return mixed
     */
    public function handle($request, Closure $next, $options = []) {
        $response = $next($request);

        if (!$request->isMethodCacheable() || (!$response->getContent() && !$request->isMethod('HEAD') && !$response instanceof BinaryFileResponse && !$response instanceof StreamedResponse)) {
            return $response;
        }

        if (is_string($options)) {
            $options = $this->parseOptions($options);
        }

        if (!$response->isSuccessful()) {
            return $response;
        }

        if (isset($options['etag']) && $options['etag'] === true) {
            $options['etag'] = $response->getEtag() ?: ($response->getContent() ? md5($response->getContent()) : null);
        }

        if (isset($options['last_modified'])) {
            $options['last_modified'] = is_numeric($options['last_modified'])
                ? CCarbon::createFromTimestamp($options['last_modified'], date_default_timezone_get())
                : CCarbon::parse($options['last_modified']);
        }

        $response->setCache($options);
        $response->isNotModified($request);

        return $response;
    }

    /**
     * @param string $options
     *
     * @return array
     */
    protected function parseOptions($options) {
        $parsed = [];

        foreach (explode(';', rtrim($options, ';')) as $option) {
            $data = explode('=', $option, 2);
            $parsed[$data[0]] = isset($data[1]) ? $data[1] : true;
        }

        return $parsed;
    }
}
