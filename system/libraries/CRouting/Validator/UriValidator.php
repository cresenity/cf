<?php

/**
 * Description of UriValidator.
 */
class CRouting_Validator_UriValidator implements CRouting_ValidatorInterface {
    /**
     * Validate a given rule against a route and request.
     *
     * @param CRouting_Route $route
     * @param CHTTP_Request  $request
     *
     * @return bool
     */
    public function matches(CRouting_Route $route, CHTTP_Request $request) {
        $path = rtrim($request->getPathInfo(), '/') ?: '/';

        return preg_match($route->getCompiled()->getRegex(), rawurldecode($path));
    }
}
