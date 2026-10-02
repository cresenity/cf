<?php

class CBase_Defer_DeferredCallbackCollection implements ArrayAccess, Countable {
    /**
     * All of the deferred callbacks.
     *
     * @var array
     */
    protected array $callbacks = [];

    /**
     * Koleksi bersama satu proses; c::defer() mengisinya dan akhir request menjalankannya.
     *
     * @return static
     */
    public static function instance() {
        $container = CContainer::getInstance();
        if (!$container->bound(static::class)) {
            $container->singleton(static::class);
        }

        return $container->make(static::class);
    }

    /**
     * Get the first callback in the collection.
     *
     * @return callable
     */
    public function first() {
        return array_values($this->callbacks)[0];
    }

    /**
     * Invoke the deferred callbacks.
     *
     * @return void
     */
    public function invoke() {
        $this->invokeWhen(function () {
            return true;
        });
    }

    /**
     * Invoke the deferred callbacks if the given truth test evaluates to true.
     *
     * @param null|\Closure $when
     *
     * @return void
     */
    public function invokeWhen($when = null) {
        $when ??= fn () => true;

        $this->forgetDuplicates();

        foreach ($this->callbacks as $index => $callback) {
            if ($when($callback)) {
                c::rescue($callback);
            }

            unset($this->callbacks[$index]);
        }
    }

    /**
     * Remove any deferred callbacks with the given name.
     *
     * @param string $name
     *
     * @return void
     */
    public function forget(string $name) {
        $this->callbacks = (new CCollection($this->callbacks))
            ->reject(function ($callback) use ($name) {
                return $callback->name === $name;
            })
            ->values()
            ->all();
    }

    /**
     * Remove any duplicate callbacks.
     *
     * @return $this
     */
    protected function forgetDuplicates() {
        $this->callbacks = (new CCollection($this->callbacks))
            ->reverse()
            ->unique(function ($c) {
                return $c->name;
            })->reverse()
            ->values()
            ->all();

        return $this;
    }

    /**
     * Determine if the collection has a callback with the given key.
     *
     * @param mixed $offset
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset) {
        $this->forgetDuplicates();

        return isset($this->callbacks[$offset]);
    }

    /**
     * Get the callback with the given key.
     *
     * @param mixed $offset
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) {
        $this->forgetDuplicates();

        return $this->callbacks[$offset];
    }

    /**
     * Set the callback with the given key.
     *
     * @param mixed $offset
     * @param mixed $value
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->callbacks[] = $value;
        } else {
            $this->callbacks[$offset] = $value;
        }
    }

    /**
     * Remove the callback with the given key from the collection.
     *
     * @param mixed $offset
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset) {
        $this->forgetDuplicates();

        unset($this->callbacks[$offset]);
    }

    /**
     * Determine how many callbacks are in the collection.
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count() {
        $this->forgetDuplicates();

        return count($this->callbacks);
    }
}
