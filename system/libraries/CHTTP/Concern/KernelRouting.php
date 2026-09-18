<?php

/**
 * Description of KernelRouting.
 */
trait CHTTP_Concern_KernelRouting {
    /**
     * Send the given request through the middleware / router.
     *
     * @param \CHTTP_Request $request
     *
     * @return \CHTTP_Response
     */
    protected function sendRequestThroughRouter($request) {
        return (new CHTTP_Pipeline())
            ->send($request)
            ->through(CHTTP::shouldSkipMiddleware() ? [] : CMiddleware::middleware())
            ->then($this->dispatchToRouter());
    }

    /**
     * Get the route dispatcher callback.
     *
     * @return \Closure
     */
    protected function dispatchToRouter() {
        return function ($request) {
            return CRouting::router()->dispatch($request);
        };
    }
}
