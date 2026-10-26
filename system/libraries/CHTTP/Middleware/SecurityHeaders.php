<?php

/**
 * Adds a small set of response headers that are safe for every app in every environment
 * (no iframe-embedding or HTTPS/HSTS trade-offs involved) - registered globally, see
 * `system/bootstrap.php`. `X-Frame-Options` and `Strict-Transport-Security` are deliberately
 * NOT here: both have real per-app trade-offs (iframe embedding, certificate-failure lockout)
 * and stay opt-in instead, same as `CHTTP_Middleware_FrameGuard`.
 */
class CHTTP_Middleware_SecurityHeaders {
    /**
     * @param CHTTP_Request $request
     * @param Closure       $next
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle($request, Closure $next) {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff', false);
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);

        return $response;
    }
}
