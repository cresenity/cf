<?php

/**
 * Adds `Strict-Transport-Security`. Opt-in, not registered by default: a domain whose HTTPS
 * isn't reliably stable (certbot/Cloudflare failures) can get locked out of HTTP fallback for
 * the whole max-age window, with no bypass for certificate errors either. Browsers ignore this
 * header entirely on a plain-HTTP response, so it is harmless on local/dev HTTP.
 */
class CHTTP_Middleware_Hsts {
    /**
     * @param CHTTP_Request $request
     * @param Closure       $next
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle($request, Closure $next) {
        $response = $next($request);

        $maxAge = (int) CF::config('app.security.hsts_max_age', 86400);
        $response->headers->set('Strict-Transport-Security', 'max-age=' . $maxAge, false);

        return $response;
    }
}
