<?php

class CApp_Visitor_Middleware_LogVisitMiddleware {
    /**
     * Handle an incoming request.
     *
     * @param \CHTTP_Request $request
     * @param \Closure       $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next) {
        $logHasSaved = false;

        // create log for first binded model
        foreach ($request->route()->parameters() as $parameter) {
            if ($parameter instanceof CModel) {
                c::visitor()->visit($parameter);

                $logHasSaved = true;

                break;
            }
        }

        // create log for normal visits
        if (!$logHasSaved) {
            c::visitor()->visit();
        }

        return $next($request);
    }
}
