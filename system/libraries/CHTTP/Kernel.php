<?php

/**
 * Description of Kernel.
 */

use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CHTTP_Kernel {
    use CHTTP_Trait_OutputBufferTrait,
        CHTTP_Concern_KernelRouting;

    /**
     * The application's middleware stack.
     *
     * @var array
     */
    protected $middleware = [];

    protected $isHandled = false;

    protected $terminated;

    /**
     * Current controller running on HTTP Kernel.
     *
     * @var CController
     */
    protected $controller;

    public function __construct() {
        $this->terminated = false;
    }

    /**
     * Report the exception to the exception handler.
     *
     * @param \Exception $e
     *
     * @return void
     */
    protected function reportException($e) {
        CException::exceptionHandler()->report($e);
    }

    /**
     * Render the exception to a response.
     *
     * @param \CHTTP_Request $request
     * @param \Exception     $e
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function renderException($request, $e) {
        return CException::exceptionHandler()->render($request, $e);
    }

    /**
     * Get current controller executed.
     *
     * @return CController
     */
    public function controller() {
        return $this->controller;
    }

    public function sendRequest($request) {
        $this->startOutputBuffering();

        $kernel = $this;
        register_shutdown_function(function () use ($kernel) {
            if (!$kernel->isHandled()) {
                $output = $kernel->cleanOutputBuffer();
                if (strlen($output) > 0) {
                    echo $output;
                }
            }
        });
        $output = '';
        $response = null;

        try {
            $response = $this->sendRequestThroughRouter($request);
        } catch (Exception $e) {
            throw $e;
        } finally {
            $output = $this->cleanOutputBuffer();
        }
        if ($response instanceof CInterface_Responsable) {
            $response = $response->toResponse($request);
        }
        if ($response == null || is_bool($response)) {
            //collect the header
            $response = c::response($output);

            if (!headers_sent()) {
                $headers = headers_list();
                foreach ($headers as $header) {
                    $headerExploded = explode(':', $header);
                    $headerKey = carr::get($headerExploded, 0);
                    $headerValue = implode(':', array_splice($headerExploded, 1));

                    if (strtolower($headerKey) != 'set-cookie') {
                        $response->headers->set($headerKey, $headerValue);
                    }
                }
            }
        }

        $response = $this->toResponse($request, $response);

        return $response;
    }

    public function handleRequest(CHTTP_Request $request) {
        $responseCache = CHTTP_ResponseCache::instance();

        if ($responseCache->hasCache()) {
            if ($responseCache->hasBeenCached($request)) {
                CEvent::dispatch(new CHTTP_ResponseCache_Event_CacheHit($request));

                $response = $responseCache->getCachedResponseFor($request);

                return $response;
            }
        }

        $response = $this->sendRequest($request);
        if ($responseCache->hasCache() && $responseCache->isEnabled()) {
            if ($responseCache->shouldCache($request, $response)) {
                $responseCache->makeReplacementsAndCacheResponse($request, $response);
                CEvent::dispatch(new CHTTP_ResponseCache_Event_CacheMissed($request));
            }
        }

        return $response;
    }

    public function handle(CHTTP_Request $request) {
        CHTTP::setRequest($request);
        $response = null;

        if ($response = CF::isDownForMaintenance()) {
            if (!$response instanceof SymfonyResponse) {
                $response = c::response('Down For Maintenance', 503);
            }
        }

        if ($response == null) {
            try {
                $response = $this->handleRequest($request);
            } catch (Exception $e) {
                $this->reportException($e);
                $response = $this->renderException($request, $e);
            } catch (Throwable $e) {
                $this->reportException($e);

                $response = $this->renderException($request, $e);
            }
        }
        CEvent::dispatch(new CHTTP_Event_RequestHandled($request, $response));

        $this->isHandled = true;

        return $response;
    }

    public function terminate($request, $response) {
        $this->terminateMiddleware($request, $response);
        CF::terminate();
        if (!$this->terminated) {
            $this->terminated = true;
        }
    }

    /**
     * Call the terminate method on any terminable middleware.
     *
     * @param \CHTTP_Request  $request
     * @param \CHTTP_Response $response
     *
     * @return void
     */
    protected function terminateMiddleware($request, $response) {
        $middlewares = CHTTP::shouldSkipMiddleware() ? [] : array_merge(
            $this->gatherRouteMiddleware($request),
            $this->middleware
        );

        foreach ($middlewares as $middleware) {
            if (!is_string($middleware)) {
                continue;
            }

            list($name) = $this->parseMiddleware($middleware);

            $instance = c::container()->make($name);

            if (method_exists($instance, 'terminate')) {
                $instance->terminate($request, $response);
            }
        }
    }

    /**
     * Gather the route middleware for the given request.
     *
     * @param \CHTTP_Request $request
     *
     * @return array
     */
    protected function gatherRouteMiddleware($request) {
        if ($route = $request->route()) {
            return CRouting::router()->gatherRouteMiddleware($route);
        }

        return [];
    }

    /**
     * Parse a middleware string to get the name and parameters.
     *
     * @param string $middleware
     *
     * @return array
     */
    protected function parseMiddleware($middleware) {
        list($name, $parameters) = array_pad(explode(':', $middleware, 2), 2, []);

        if (is_string($parameters)) {
            $parameters = explode(',', $parameters);
        }

        return [$name, $parameters];
    }

    public function isHandled() {
        return $this->isHandled;
    }

    /**
     * Static version of prepareResponse.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param mixed                                     $response
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public static function toResponse($request, $response) {
        if ($response instanceof CInterface_Responsable) {
            $response = $response->toResponse($request);
        }

        if ($response instanceof PsrResponseInterface) {
            $response = (new HttpFoundationFactory())->createResponse($response);
        } elseif ($response instanceof CModel && $response->wasRecentlyCreated) {
            $response = new CHTTP_JsonResponse($response, 201);
        } elseif (!$response instanceof SymfonyResponse
            && ($response instanceof Arrayable
            || $response instanceof Jsonable
            || $response instanceof ArrayObject
            || $response instanceof JsonSerializable
            || is_array($response))
        ) {
            $response = new CHTTP_JsonResponse($response);
        } elseif (!$response instanceof SymfonyResponse) {
            $response = new CHTTP_Response($response, 200, ['Content-Type' => 'text/html']);
        }

        if ($response->getStatusCode() === CHTTP_Response::HTTP_NOT_MODIFIED) {
            $response->setNotModified();
        }

        //CFEvent::run('system.send_headers');
        $preparedResponse = $response->prepare($request);

        return $preparedResponse;
    }
}
