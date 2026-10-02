<?php

class CHTTP_Middleware_TrimStrings extends CHTTP_Middleware_TransformRequest {
    /**
     * All of the registered skip callbacks.
     *
     * @var array
     */
    protected static $skipCallbacks = [];

    /**
     * The attributes that should never be trimmed, in addition to $except.
     *
     * @var array
     */
    protected static $neverTrim = [];

    /**
     * The attributes that should not be trimmed.
     *
     * @var array
     */
    protected $except = [

    ];

    /**
     * Handle an incoming request.
     *
     * @param \CHTTP_Request $request
     * @param \Closure       $next
     *
     * @return mixed
     */
    public function handle($request, $next) {
        foreach (static::$skipCallbacks as $callback) {
            if ($callback($request)) {
                return $next($request);
            }
        }

        return parent::handle($request, $next);
    }

    /**
     * Transform the given value.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     */
    protected function transform($key, $value) {
        if ($this->shouldSkip($key, array_merge($this->except, static::$neverTrim))) {
            return $value;
        }

        return is_string($value) ? trim($value) : $value;
    }

    /**
     * Determine if the given key should be skipped; entries may use `*` as a wildcard.
     *
     * @param string $key
     * @param array  $except
     *
     * @return bool
     */
    protected function shouldSkip($key, $except) {
        foreach ($except as $pattern) {
            if ($pattern === $key || (is_string($pattern) && strpos($pattern, '*') !== false && cstr::is($pattern, $key))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Indicate that the given attributes should never be trimmed.
     *
     * @param array|string $attributes
     *
     * @return void
     */
    public static function except($attributes) {
        static::$neverTrim = array_values(array_unique(array_merge(static::$neverTrim, (array) $attributes)));
    }

    /**
     * Flush the global state of the middleware.
     *
     * @return void
     */
    public static function flushState() {
        static::$neverTrim = [];
        static::$skipCallbacks = [];
    }

    /**
     * Register a callback that instructs the middleware to be skipped.
     *
     * @param \Closure $callback
     *
     * @return void
     */
    public static function skipWhen($callback) {
        static::$skipCallbacks[] = $callback;
    }
}
