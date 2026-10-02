<?php

use Symfony\Component\HttpFoundation\Response;

/**
 * @see https://github.com/fruitcake/laravel-cors
 */
class CHTTP_Middleware_HandleCors {
    /**
     * @var null|array
     */
    protected $paths;

    public function __construct() {
    }

    /**
     * Handle an incoming request. Based on Asm89\Stack\Cors by asm89.
     *
     * @param \CHTTP_Request $request
     * @param \Closure       $next
     *
     * @return Response
     */
    public function handle($request, Closure $next) {
        //register the event on request handled

        // Check if we're dealing with CORS and if we should handle it
        if (!$this->shouldRun($request)) {
            return $next($request);
        }
        CEvent::dispatcher()->listen(CHTTP_Event_RequestHandled::class, function (CHTTP_Event_RequestHandled $event) {
            $this->addHeaders($event->request, $event->response);
        });

        // For Preflight, return the Preflight response
        if (CHTTP_Cors::corsService()->isPreflightRequest($request)) {
            $response = CHTTP_Cors::corsService()->handlePreflightRequest($request);

            CHTTP_Cors::corsService()->varyHeader($response, 'Access-Control-Request-Method');

            return $response;
        }

        // Handle the request
        $response = $next($request);

        if ($request->getMethod() === 'OPTIONS') {
            CHTTP_Cors::corsService()->varyHeader($response, 'Access-Control-Request-Method');
        }

        return $this->addHeaders($request, $response);
    }

    /**
     * Add the headers to the Response, if they don't exist yet.
     *
     * @param CHTTP_Request $request
     * @param Response      $response
     *
     * @return Response
     */
    protected function addHeaders(CHTTP_Request $request, Response $response) {
        if (!$response->headers->has('Access-Control-Allow-Origin')) {
            // Add the CORS headers to the Response
            $response = CHTTP_Cors::corsService()->addActualRequestHeaders($response, $request);
        }

        return $response;
    }

    /**
     * Determine if the request has a URI that should pass through the CORS flow.
     *
     * @param \CHTTP_Request $request
     *
     * @return bool
     */
    protected function shouldRun(CHTTP_Request $request) {
        return $this->isMatchingPath($request);
    }

    /**
     * The the path from the config, to see if the CORS Service should run.
     *
     * @param \CHTTP_Request $request
     *
     * @return bool
     */
    protected function isMatchingPath(CHTTP_Request $request) {
        // Get the paths from the config or the middleware
        $paths = $this->getPathsByHost($request->getHost());

        foreach ($paths as $path) {
            if ($path !== '/') {
                $path = trim($path, '/');
            }

            if ($request->fullUrlIs($path) || $request->is($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Paths by given host or string values in config by default.
     *
     * @param string $host
     *
     * @return array
     */
    protected function getPathsByHost($host) {
        $paths = $this->paths ?: CF::config('http.cors.paths', []) ?: CF::config('http.cors.path', []);
        // If where are paths by given host
        if (isset($paths[$host])) {
            return $paths[$host];
        }
        // Defaults
        return array_filter($paths, function ($path) {
            return is_string($path);
        });
    }
}
