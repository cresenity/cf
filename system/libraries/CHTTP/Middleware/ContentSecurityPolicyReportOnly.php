<?php

/**
 * Adds `Content-Security-Policy-Report-Only` - never `Content-Security-Policy` (enforcing).
 * Report-only never blocks anything, so this is safe to opt into even for an unaudited policy;
 * it exists to observe real violations first. Opt-in, not registered by default: the policy
 * is app-specific (script/style/font/image sources differ per app). Value comes from
 * `app.security.csp_report_only`; the header is skipped entirely when that config is empty.
 */
class CHTTP_Middleware_ContentSecurityPolicyReportOnly {
    /**
     * @param CHTTP_Request $request
     * @param Closure       $next
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle($request, Closure $next) {
        $response = $next($request);

        $policy = CF::config('app.security.csp_report_only');
        if (!empty($policy)) {
            $response->headers->set('Content-Security-Policy-Report-Only', $policy, false);
        }

        return $response;
    }
}
