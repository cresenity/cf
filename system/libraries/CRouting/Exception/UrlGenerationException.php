<?php

class CRouting_Exception_UrlGenerationException extends Exception {
    /**
     * Create a new exception for missing route parameters.
     *
     * @param CRouting_Route $route
     * @param array          $parameters
     *
     * @return static
     */
    public static function forMissingParameters($route, array $parameters = []) {
        $parameterLabel = count($parameters) === 1 ? 'parameter' : 'parameters';

        $message = sprintf('Missing required %s for [Route: %s] [URI: %s]', $parameterLabel, $route->getName(), $route->uri());

        if ($parameters !== []) {
            $message .= sprintf(' [Missing %s: %s]', $parameterLabel, implode(', ', $parameters));
        }

        return new static($message . '.');
    }
}
