<?php

/**
 * Description of SchemeValidator.
 */
class CRouting_Validator_SchemeValidator implements CRouting_ValidatorInterface {
    /**
     * Validate a given rule against a route and request.
     *
     * @param CRouting_Route $route
     * @param CHTTP_Request  $request
     *
     * @return bool
     */
    public function matches(CRouting_Route $route, CHTTP_Request $request) {
        if ($route->httpOnly()) {
            return !$request->secure();
        } elseif ($route->secure()) {
            return $request->secure();
        }

        return true;
    }
}
