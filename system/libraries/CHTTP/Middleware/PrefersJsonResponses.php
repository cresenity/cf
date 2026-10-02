<?php

/**
 * Treats a missing or catch-all Accept header (`*\/*`, `application/*`) as `application/json`.
 */
class CHTTP_Middleware_PrefersJsonResponses {
    /**
     * @param CHTTP_Request $request
     * @param Closure       $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next) {
        $accept = $request->headers->get('Accept');

        if ($this->acceptHeaderIsBroad($accept)) {
            if ($accept !== null) {
                $request->headers->set('X-Original-Accept', $accept);
            }

            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }

    /**
     * @param null|string $accept
     *
     * @return bool
     */
    protected function acceptHeaderIsBroad($accept) {
        if ($accept === null || trim($accept) === '') {
            return true;
        }

        foreach (explode(',', $accept) as $value) {
            $value = strtolower(trim($value));

            if ($value === '') {
                continue;
            }

            $position = strpos($value, ';');
            if ($position !== false) {
                $value = trim(substr($value, 0, $position));
            }

            if (!in_array($value, ['*/*', 'application/*'], true)) {
                return false;
            }
        }

        return true;
    }
}
