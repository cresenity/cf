<?php

class CHTTP_Middleware_ConvertEmptyStringsToNull extends CHTTP_Middleware_TransformRequest {
    /**
     * All of the registered skip callbacks.
     *
     * @var array
     */
    protected static $skipCallbacks = [];

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
        return is_string($value) && $value === '' ? null : $value;
    }

    /**
     * Flush the global state of the middleware.
     *
     * @return void
     */
    public static function flushState() {
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
