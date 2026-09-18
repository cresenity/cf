<?php
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CApi_Kernel {
    use CApi_Trait_HasGroupPropertyTrait;

    protected $isHandled = false;

    /**
     * Create a new API kernel instance.
     *
     * @param string $group
     */
    public function __construct($group) {
        $this->group = $group;
    }

    public function handle(CHTTP_Request $request, Closure $methodResolver) {
        try {
            if (!$request instanceof CApi_HTTP_Request) {
                $request = CApi_HTTP_Request::createFromBaseHttp($request);
            }
            if ($request->group() === null) {
                $request->setGroup($this->group);
            }
            CEvent::dispatch(new CApi_Event_IncomingRequest($request));
            $response = $this->sendRequestThroughPipeline($request, $methodResolver);
        } catch (Exception $e) {
            $this->reportException($e);
            $response = $this->renderException($request, $e);
        } catch (Throwable $e) {
            $this->reportException($e);
            $response = $this->renderException($request, $e);
        }

        CEvent::dispatch(new CApi_Event_RequestHandled($request, $response));

        $this->isHandled = true;

        return $response;
    }

    /**
     * Send the given request through the middleware / router.
     *
     * @param \CApi_HTTP_Request $request
     * @param mixed              $methodResolver
     *
     * @return \CApi_HTTP_Response
     */
    protected function sendRequestThroughPipeline(CApi_HTTP_Request $request, $methodResolver) {
        $method = $methodResolver($request);

        return (new CApi_HTTP_Pipeline($this->group))
            ->send($request)
            ->through(c::api($this->group)->shouldSkipMiddleware() ? [] : $this->gatherMiddleware($method))
            ->then($this->dispatchMethod($method));
    }

    /**
     * Get the route dispatcher callback.
     *
     * @param CApi_MethodAbstract $method
     *
     * @return \Closure
     */
    protected function dispatchMethod(CApi_MethodAbstract $method) {
        return function ($request) use ($method) {
            CEvent::dispatch(new CApi_Event_BeforeDispatch($method));
            $response = null;
            $post = 'post';
            $get = 'get';
            $put = 'put';
            $delete = 'delete';
            $patch = 'patch';
            if ($request->isMethod('post') && method_exists($method, $post)) {
                $response = $method->$post();
            } elseif ($request->isMethod('get') && method_exists($method, $get)) {
                $response = $method->$get();
            } elseif ($request->isMethod('put') && method_exists($method, $put)) {
                $response = $method->$put();
            } elseif ($request->isMethod('delete') && method_exists($method, $delete)) {
                $response = $method->$delete();
            } elseif ($request->isMethod('patch') && method_exists($method, $patch)) {
                $response = $method->$patch();
            } else {
                $response = $method->execute();
            }

            $isResponse = $response instanceof SymfonyResponse;

            if (!$isResponse) {
                $methodResponse = new CApi_MethodResponse($request, $method);
                $response = $methodResponse->toResponse();
            }
            CEvent::dispatch(new CApi_Event_AfterDispatch($response));

            return $response;
        };
    }

    protected function gatherMiddleware(CApi_MethodAbstract $method) {
        if (!method_exists($method, 'getMiddleware')) {
            return [];
        }

        return c::collect($method->getMiddleware())->pluck('middleware')->all();
    }

    /**
     * Prepare a response by transforming and formatting it correctly.
     *
     * @param mixed              $response
     * @param \CApi_HTTP_Request $request
     *
     * @return \CApi_HTTP_Response
     */
    protected function prepareResponse($response, CApi_HTTP_Request $request) {
        if ($response instanceof CApi_MethodResponse) {
            $response = $response->toResponse();
        }
        if ($response instanceof CHTTP_Response) {
            $response = CApi_HTTP_Response::makeFromExisting($response);
        } elseif ($response instanceof CHTTP_JsonResponse) {
            $response = CApi_HTTP_Response::makeFromJson($response);
        }

        // if ($response->isSuccessful() && $this->requestIsConditional()) {
        //     if (!$response->headers->has('ETag')) {
        //         $response->setEtag(sha1($response->getContent() ?: ''));
        //     }

        //     $response->isNotModified($request);
        // }

        return $response;
    }
}
