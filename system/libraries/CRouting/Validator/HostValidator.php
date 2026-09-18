<?php

/**
 * Description of HostValidator.
 */
class CRouting_Validator_HostValidator implements CRouting_ValidatorInterface {
    /**
     * Validate a given rule against a route and request.
     *
     * @param CRouting_Route $route
     * @param CHTTP_Request  $request
     *
     * @return bool
     */
    public function matches(CRouting_Route $route, CHTTP_Request $request) {
        $hostRegex = $route->getCompiled()->getHostRegex();

        if (is_null($hostRegex)) {
            return true;
        }

        return preg_match($hostRegex, $request->getHost());
    }
}
