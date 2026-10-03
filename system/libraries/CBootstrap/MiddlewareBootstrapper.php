<?php

/**
 * Description of MiddlewareBootstrapper.
 */
class CBootstrap_MiddlewareBootstrapper extends CBootstrap_BootstrapperAbstract {
    /**
     * Bootstrap the given application.
     *
     * @return void
     */
    public function bootstrap() {
        CMiddleware::manager()->pushMiddleware(CHTTP_Cookie_Middleware_AddQueuedCookiesToResponse::class);
        CMiddleware::manager()->pushMiddleware(CHTTP_Middleware_CleanInput::class);
        $statelessPaths = (array) CF::config('session.stateless_paths', []);
        if (!c::request()->is('cresenity/auth/ping') && !($statelessPaths && c::request()->is(...$statelessPaths))) {
            CMiddleware::manager()->pushMiddleware(CSession_Middleware_SessionMiddleware::class);
            if (CF::config('session.authenticate')) {
                CMiddleware::manager()->pushMiddleware(CAuth_Middleware_AuthenticateSession::class);
            }
            CMiddleware::manager()->pushMiddleware(CView_Middleware_ShareErrorsFromSession::class);
        }
    }
}
