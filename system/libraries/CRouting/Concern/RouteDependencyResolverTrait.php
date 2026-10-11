<?php

/**
 * Description of RouteDependencyResolverTrait.
 */
trait CRouting_Concern_RouteDependencyResolverTrait {
    /**
     * Resolve the object method's type-hinted dependencies.
     *
     * @param array  $parameters
     * @param object $instance
     * @param string $method
     *
     * @return array
     */
    protected function resolveClassMethodDependencies(array $parameters, $instance, $method) {
        if (!method_exists($instance, $method)) {
            return $parameters;
        }

        return $this->resolveMethodDependencies(
            $parameters,
            new ReflectionMethod($instance, $method)
        );
    }

    /**
     * Resolve the given method's type-hinted dependencies.
     *
     * @param array                       $parameters
     * @param \ReflectionFunctionAbstract $reflector
     *
     * @return array
     */
    public function resolveMethodDependencies(array $parameters, ReflectionFunctionAbstract $reflector) {
        foreach ($reflector->getParameters() as $key => $parameter) {
            // If the parameter has a type-hinted class, we will check to see if it is already in
            // the list of parameters. If it is we will just skip it as it is probably a model
            // binding and we do not want to mess with those; otherwise, we resolve it here.
            $className = CBase_Reflector::getParameterClassName($parameter);

            if ($className && !$this->alreadyInParameters($className, $parameters)) {
                array_splice(
                    $parameters,
                    $key,
                    0,
                    [$this->container->make($className)]
                );
            }
        }

        return $parameters;
    }

    /**
     * Determine if an object of the given class is in a list of parameters.
     *
     * @param string $class
     * @param array  $parameters
     *
     * @return bool
     */
    protected function alreadyInParameters($class, array $parameters) {
        return !is_null(carr::first($parameters, function ($value) use ($class) {
            return $value instanceof $class;
        }));
    }
}
