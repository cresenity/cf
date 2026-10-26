<?php

/**
 * Adds `Permissions-Policy`. Opt-in, not registered by default: the value is app-specific
 * (an app using the camera/geolocation/payment APIs would break). Value comes from
 * `app.security.permissions_policy` so each app states what it actually uses instead of
 * inheriting a one-size policy; the header is skipped entirely when that config is empty.
 */
class CHTTP_Middleware_PermissionsPolicy {
    /**
     * @param CHTTP_Request $request
     * @param Closure       $next
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle($request, Closure $next) {
        $response = $next($request);

        $policy = CF::config('app.security.permissions_policy');
        if (!empty($policy)) {
            $response->headers->set('Permissions-Policy', $policy, false);
        }

        return $response;
    }
}
