<?php

abstract class CRedis_AbstractConnection implements CRedis_ConnectionInterface {
    /**
     * The Redis client.
     *
     * @var \Predis\ClientInterface
     */
    protected $client;

    /**
     * The Redis connection name.
     *
     * @var null|string
     */
    protected $name;

    /**
     * The event dispatcher instance.
     *
     * @var CEvent_Dispatcher
     */
    protected $events;

    /**
     * Subscribe to a set of given channels for messages.
     *
     * @param array|string $channels
     * @param \Closure     $callback
     * @param string       $method
     *
     * @return void
     */
    abstract public function createSubscription($channels, Closure $callback, $method = 'subscribe');

    /**
     * Funnel a callback for a maximum number of simultaneous executions.
     *
     * @param string $name
     *
     * @return CRedis_Limiter_ConcurrencyLimiterBuilder
     */
    public function funnel($name) {
        return new CRedis_Limiter_ConcurrencyLimiterBuilder($this, $name);
    }

    /**
     * Throttle a callback for a maximum number of executions over a given duration.
     *
     * @param string $name
     *
     * @return CRedis_Limiter_DurationLimiterBuilder
     */
    public function throttle($name) {
        return new CRedis_Limiter_DurationLimiterBuilder($this, $name);
    }

    /**
     * Get the underlying Redis client.
     *
     * @return \Predis\ClientInterface
     */
    public function client() {
        return $this->client;
    }

    /**
     * Subscribe to a set of given channels for messages.
     *
     * @param array|string $channels
     * @param \Closure     $callback
     *
     * @return void
     */
    public function subscribe($channels, Closure $callback) {
        return $this->createSubscription($channels, $callback, __FUNCTION__);
    }

    /**
     * Subscribe to a set of given channels with wildcards.
     *
     * @param array|string $channels
     * @param \Closure     $callback
     *
     * @return void
     */
    public function psubscribe($channels, Closure $callback) {
        return $this->createSubscription($channels, $callback, __FUNCTION__);
    }

    /**
     * Run a command against the Redis database.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function command($method, array $parameters = []) {
        $start = microtime(true);
        $result = $this->client->{$method}(...$parameters);
        $time = round((microtime(true) - $start) * 1000, 2);
        if (isset($this->events)) {
            $this->event(new CRedis_Event_CommandExecuted($method, $parameters, $time, $this));
        }

        return $result;
    }

    /**
     * Fire the given event if possible.
     *
     * @param mixed $event
     *
     * @return void
     */
    protected function event($event) {
        if (isset($this->events)) {
            $this->events->dispatch($event);
        }
    }

    /**
     * Register a Redis command listener with the connection.
     *
     * @param \Closure $callback
     *
     * @return void
     */
    public function listen(Closure $callback) {
        if (isset($this->events)) {
            $this->events->listen(CommandExecuted::class, $callback);
        }
    }

    /**
     * Get the connection name.
     *
     * @return null|string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Set the connections name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName($name) {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the event dispatcher used by the connection.
     *
     * @return \CEvent_DispatcherInterface
     */
    public function getEventDispatcher() {
        return $this->events;
    }

    /**
     * Set the event dispatcher instance on the connection.
     *
     * @param CEvent_DispatcherInterface $events
     *
     * @return void
     */
    public function setEventDispatcher(CEvent_Dispatcher $events) {
        $this->events = $events;
    }

    /**
     * Unset the event dispatcher instance on the connection.
     *
     * @return void
     */
    public function unsetEventDispatcher() {
        $this->events = null;
    }

    /**
     * Pass other method calls down to the underlying client.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters) {
        return $this->command($method, $parameters);
    }
}
