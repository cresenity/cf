<?php

/**
 * Adds the enforcing `Content-Security-Policy` (blocks violations, unlike
 * `ContentSecurityPolicyReportOnly`). Opt-in, not registered by default - same reasoning as
 * `ContentSecurityPolicyReportOnly`, but the stakes are higher: a wrong policy here breaks the
 * page, not just logs a warning. Only register this after `ContentSecurityPolicyReportOnly`
 * has been used to find the real policy a page needs. Value comes from
 * `app.security.csp`; the header is skipped entirely when that config is empty.
 */
class CHTTP_Middleware_ContentSecurityPolicy {
    /**
     * @param CHTTP_Request $request
     * @param Closure       $next
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle($request, Closure $next) {
        $response = $next($request);

        $policy = CF::config('app.security.csp');
        if (!empty($policy)) {
            $response->headers->set('Content-Security-Policy', $policy, false);
        }

        return $response;
    }
}
