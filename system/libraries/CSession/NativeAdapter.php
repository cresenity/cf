<?php

/**
 * Description of NativeAdapter
 * will assigned to $_SESSION
 */
class CSession_NativeAdapter implements \ArrayAccess {
    #[\ReturnTypeWillChange]
    public function offsetExists($offset) {
        return c::session()->has($offset);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset) {
        return c::session()->get($offset);
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value) {
        return c::session()->put($offset, $value);
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset) {
        return c::session()->forget($offset);
    }

    public function data() {
        return c::session()->all();
    }
}
