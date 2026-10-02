<?php

/**
 * Adds `X-Frame-Options: SAMEORIGIN` to responses. Not registered by default: apps that are embedded in iframes would break.
 */
class CHTTP_Middleware_FrameGuard {
    /**
     * @param CHTTP_Request $request
     * @param Closure       $next
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle($request, Closure $next) {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN', false);

        return $response;
    }
}
