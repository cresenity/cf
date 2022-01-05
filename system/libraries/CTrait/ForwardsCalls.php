<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan <hery@itton.co.id>
 * @license Ittron Global Teknologi
 *
 * @since Aug 26, 2020
 */
trait CTrait_ForwardsCalls {
    /**
     * Forward a method call to the given object.
     *
     * @param mixed  $object
     * @param string $method
     * @param array  $parameters
     *
     * @throws \BadMethodCallException
     *
     * @return mixed
     */
    protected function forwardCallTo($object, $method, $parameters) {
        try {
            return $object->{$method}(...$parameters);
        } catch (BadMethodCallException $e) {
            $pattern = '~^Call to undefined method (?P<class>[^:]+)::(?P<method>[^\(]+)\(\)$~';

            if (!preg_match($pattern, $e->getMessage(), $matches)) {
                throw $e;
            }

            if ($matches['class'] != get_class($object)
                || $matches['method'] != $method
            ) {
                throw $e;
            }

            static::throwBadMethodCallException($method);
        } catch (Error $e) {
            $pattern = '~^Call to undefined method (?P<class>[^:]+)::(?P<method>[^\(]+)\(\)$~';

            if (!preg_match($pattern, $e->getMessage(), $matches)) {
                throw $e;
            }

            if ($matches['class'] != get_class($object)
                || $matches['method'] != $method
            ) {
                throw $e;
            }

            static::throwBadMethodCallException($method);
        }
    }

    /**
     * Throw a bad method call exception for the given method.
     *
     * @param string $method
     *
     * @throws \BadMethodCallException
     *
     * @return void
     */
    protected static function throwBadMethodCallException($method) {
        throw new BadMethodCallException(sprintf(
            'Call to undefined method %s::%s()',
            static::class,
            $method
        ));
    }
}
