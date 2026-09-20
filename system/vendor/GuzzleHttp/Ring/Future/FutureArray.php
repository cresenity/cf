<?php
namespace GuzzleHttp\Ring\Future;

/**
 * Represents a future array value that when dereferenced returns an array.
 */
class FutureArray implements FutureArrayInterface
{
    use MagicFutureTrait;
    use BaseFutureTrait {
        BaseFutureTrait::__construct as private baseFutureConstruct;
    }

    public function __construct(\React\Promise\PromiseInterface $promise, callable $wait = null, callable $cancel = null)
    {
        $this->baseFutureConstruct($promise, $wait, $cancel);
        // let __get() lazily create it, same as before $_value was declared
        unset($this->_value);
    }

    public function offsetExists($offset)
    {
        return isset($this->_value[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->_value[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->_value[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->_value[$offset]);
    }

    public function count()
    {
        return count($this->_value);
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->_value);
    }
}
