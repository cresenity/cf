<?php

/**
 * Description of MethodValidator.
 */
class CRouting_Validator_MethodValidator implements CRouting_ValidatorInterface {
    /**
     * Validate a given rule against a route and request.
     *
     * @param CRouting_Route $route
     * @param CHTTP_Request  $request
     *
     * @return bool
     */
    public function matches(CRouting_Route $route, CHTTP_Request $request) {
        return in_array($request->getMethod(), $route->methods());
    }
}
