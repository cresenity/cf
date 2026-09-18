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
        //support for php 5.6
        if (!method_exists($reflector, 'getType')) {
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

        $instanceCount = 0;

        $values = array_values($parameters);

        $skippableValue = new \stdClass();

        foreach ($reflector->getParameters() as $key => $parameter) {
            $instance = $this->transformDependency($parameter, $parameters, $skippableValue);

            if ($instance !== $skippableValue) {
                $instanceCount++;

                $this->spliceIntoParameters($parameters, $key, $instance);
            } elseif (!isset($values[$key - $instanceCount])
                && $parameter->isDefaultValueAvailable()
            ) {
                $this->spliceIntoParameters($parameters, $key, $parameter->getDefaultValue());
            }
        }

        return $parameters;
    }

    /**
     * Attempt to transform the given parameter into a class instance.
     *
     * @param \ReflectionParameter $parameter
     * @param array                $parameters
     * @param object               $skippableValue
     *
     * @return mixed
     */
    protected function transformDependency(ReflectionParameter $parameter, $parameters, $skippableValue) {
        $className = CBase_Reflector::getParameterClassName($parameter);

        // If the parameter has a type-hinted class, we will check to see if it is already in
        // the list of parameters. If it is we will just skip it as it is probably a model
        // binding and we do not want to mess with those; otherwise, we resolve it here.
        if ($className && !$this->alreadyInParameters($className, $parameters)) {
            return $parameter->isDefaultValueAvailable() ? null : CContainer::getInstance()->make($className);
        }

        return $skippableValue;
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

    /**
     * Splice the given value into the parameter list.
     *
     * @param array  $parameters
     * @param string $offset
     * @param mixed  $value
     *
     * @return void
     */
    protected function spliceIntoParameters(array &$parameters, $offset, $value) {
        array_splice(
            $parameters,
            $offset,
            0,
            [$value]
        );
    }
}
