<?php

defined('SYSPATH') or die('No direct access allowed.');

class CContainer_BoundMethod {
    /**
     * Call the given Closure / class@method and inject its dependencies.
     *
     * @param CContainer_Container $container
     * @param callable|string      $callback
     * @param array                $parameters
     * @param null|string          $defaultMethod
     *
     * @throws \ReflectionException
     * @throws \InvalidArgumentException
     *
     * @return mixed
     */
    public static function call($container, $callback, array $parameters = [], $defaultMethod = null) {
        if (static::isCallableWithAtSign($callback) || $defaultMethod) {
            return static::callClass($container, $callback, $parameters, $defaultMethod);
        }

        return static::callBoundMethod($container, $callback, function () use ($container, $callback, $parameters) {
            return call_user_func_array(
                $callback,
                static::getMethodDependencies($container, $callback, $parameters)
            );
        });
    }

    /**
     * Call a string reference to a class using Class@method syntax.
     *
     * @param CContainer_Container $container
     * @param string               $target
     * @param array                $parameters
     * @param null|string          $defaultMethod
     *
     * @throws \InvalidArgumentException
     *
     * @return mixed
     */
    protected static function callClass($container, $target, array $parameters = [], $defaultMethod = null) {
        $segments = explode('@', $target);
        // We will assume an @ sign is used to delimit the class name from the method
        // name. We will split on this @ sign and then build a callable array that
        // we can pass right back into the "call" method for dependency binding.
        $method = count($segments) === 2 ? $segments[1] : $defaultMethod;
        if (is_null($method)) {
            throw new InvalidArgumentException('Method not provided.');
        }

        return static::call(
            $container,
            [$container->make($segments[0]), $method],
            $parameters
        );
    }

    /**
     * Call a method that has been bound to the container.
     *
     * @param CContainer_Container $container
     * @param callable             $callback
     * @param mixed                $default
     *
     * @return mixed
     */
    protected static function callBoundMethod($container, $callback, $default) {
        if (!is_array($callback)) {
            return $default instanceof Closure ? $default() : $default;
        }
        // Here we need to turn the array callable into a Class@method string we can use to
        // examine the container and see if there are any method bindings for this given
        // method. If there are, we can call this method binding callback immediately.
        $method = static::normalizeMethod($callback);
        if ($container->hasMethodBinding($method)) {
            return $container->callMethodBinding($method, $callback[0]);
        }

        return $default instanceof Closure ? $default() : $default;
    }

    /**
     * Normalize the given callback into a Class@method string.
     *
     * @param callable $callback
     *
     * @return string
     */
    protected static function normalizeMethod($callback) {
        $class = is_string($callback[0]) ? $callback[0] : get_class($callback[0]);

        return "{$class}@{$callback[1]}";
    }

    /**
     * Get all dependencies for a given method.
     *
     * @param CContainer_Container $container
     * @param callable|string      $callback
     * @param array                $parameters
     *
     * @throws \ReflectionException
     *
     * @return array
     */
    protected static function getMethodDependencies($container, $callback, array $parameters = []) {
        $dependencies = [];
        foreach (static::getCallReflector($callback)->getParameters() as $parameter) {
            static::addDependencyForCallParameter($container, $parameter, $parameters, $dependencies);
        }

        return array_merge($dependencies, $parameters);
    }

    /**
     * Get the proper reflection instance for the given callback.
     *
     * @param callable|string $callback
     *
     * @throws \ReflectionException
     *
     * @return \ReflectionFunctionAbstract
     */
    protected static function getCallReflector($callback) {
        if (is_string($callback) && strpos($callback, '::') !== false) {
            $callback = explode('::', $callback);
        } elseif (is_object($callback) && !$callback instanceof Closure) {
            // Objek invokable: yang dipanggil sebenarnya __invoke miliknya, dan
            // itu yang harus direfleksi. Tanpa cabang ini ReflectionFunction
            // menerima objek dan menolaknya.
            $callback = [$callback, '__invoke'];
        }

        return is_array($callback) ? new ReflectionMethod($callback[0], $callback[1]) : new ReflectionFunction($callback);
    }

    /**
     * Get the dependency for the given call parameter.
     *
     * @param CContainer_Container $container
     * @param \ReflectionParameter $parameter
     * @param array                $parameters
     * @param array                $dependencies
     *
     * @return void
     */
    protected static function addDependencyForCallParameter($container, $parameter, array &$parameters, &$dependencies) {
        $className = CBase_Reflector::getParameterClassName($parameter);

        if (array_key_exists($parameter->name, $parameters)) {
            $dependencies[] = $parameters[$parameter->name];
            unset($parameters[$parameter->name]);
        } elseif ($className && array_key_exists($className, $parameters)) {
            $dependencies[] = $parameters[$className];
            unset($parameters[$className]);
        } elseif ($className && $parameter->isDefaultValueAvailable() && !$container->bound($className)) {
            $dependencies[] = $parameter->getDefaultValue();
        } elseif ($className) {
            $dependencies[] = $container->make($className);
        } elseif ($parameter->isDefaultValueAvailable()) {
            $dependencies[] = $parameter->getDefaultValue();
        } elseif (!$parameter->isOptional() && !array_key_exists($parameter->name, $parameters)) {
            $declaringClass = $parameter->getDeclaringClass();
            $message = "Unable to resolve dependency [{$parameter}]" . ($declaringClass ? " in class {$declaringClass->getName()}" : '');

            throw new CContainer_Exception_BindingResolutionException($message);
        }
    }

    /**
     * Determine if the given string is in Class@method syntax.
     *
     * @param mixed $callback
     *
     * @return bool
     */
    protected static function isCallableWithAtSign($callback) {
        return is_string($callback) && strpos($callback, '@') !== false;
    }
}
