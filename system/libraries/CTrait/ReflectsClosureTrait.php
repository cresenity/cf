<?php

/**
 * Description of ReflectsClosureTrait
 *
 * @author Hery
 */
trait CTrait_ReflectsClosureTrait {
    /**
     * Get the class names / types of the parameters of the given Closure.
     *
     * @param \Closure $closure
     *
     * @throws \ReflectionException
     *
     * @return array
     */
    protected function closureParameterTypes(Closure $closure) {
        $reflection = new ReflectionFunction($closure);

        return c::collect($reflection->getParameters())->mapWithKeys(function ($parameter) {
            if ($parameter->isVariadic()) {
                return [$parameter->getName() => null];
            }

            return [$parameter->getName() => $parameter->getClass()->getName()];
        })->all();
    }

    /**
     * Get the class name of the first parameter of the given Closure.
     *
     * @param \Closure $closure
     *
     * @throws \ReflectionException|\RuntimeException
     *
     * @return string
     */
    protected function firstClosureParameterType(Closure $closure) {
        $types = array_values($this->closureParameterTypes($closure));

        if (!$types) {
            throw new RuntimeException('The given Closure has no parameters.');
        }

        if ($types[0] === null) {
            throw new RuntimeException('The first parameter of the given Closure is missing a type hint.');
        }

        return $types[0];
    }
}
