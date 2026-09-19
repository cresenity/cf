<?php

class CAuth_Middleware_ProtectFromImpersonatingMiddleware {
    /**
     * Handle an incoming request.
     *
     * @param \CHTTP_Request $request
     * @param \Closure       $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next) {
        if (CAuth::impersonateManager()->isImpersonating()) {
            return c::redirect()->back();
        }

        return $next($request);
    }
}
