<?php
use Ably\AblyRest;
use Pusher\Pusher;
use Psr\Log\LoggerInterface;

class CBroadcast_Manager implements CBroadcast_Contract_FactoryInterface {
    /**
     * The array of resolved broadcast drivers.
     *
     * @var array
     */
    protected $drivers = [];

    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $customCreators = [];

    private static $instance;

    public static function instance() {
        if (static::$instance == null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Create a new manager instance.
     *
     * @return void
     */
    public function __construct() {
    }

    /**
     * Register the routes for handling broadcast authentication and sockets.
     *
     * @param null|array $attributes
     *
     * @return void
     */
    public function routes(?array $attributes = null) {
        $attributes = $attributes ?: ['middleware' => ['web']];
        CRouting::router()->group($attributes, function ($router) {
            $router->match(
                ['get', 'post'],
                '/broadcasting/auth',
                '\\' . CBroadcast_BroadcastController::class . '@authenticate'
            )->withoutMiddleware([\CHTTP_Middleware_VerifyCsrfToken::class]);
        });
    }

    /**
     * Get the socket ID for the given request.
     *
     * @param null|\CHTTP_Request $request
     *
     * @return null|string
     */
    public function socket($request = null) {
        $request = $request ?: CHTTP::request();

        return $request->header('X-Socket-ID');
    }

    /**
     * Begin broadcasting an event.
     *
     * @param null|mixed $event
     *
     * @return \CBroadcast_PendingBroadcast
     */
    public function event($event = null) {
        return new CBroadcast_PendingBroadcast(CEvent::dispatcher(), $event);
    }

    /**
     * Queue the given event for broadcast.
     *
     * @param mixed $event
     *
     * @return void
     */
    public function queue($event) {
        if ($event instanceof CBroadcast_Contract_ShouldBroadcastNowInterface
            || (is_object($event)
            && method_exists($event, 'shouldBroadcastNow')
            && $event->shouldBroadcastNow())
        ) {
            return CQueue::dispatcher()->dispatchNow(new CBroadcast_BroadcastEvent(clone $event));
        }

        $queue = null;

        if (method_exists($event, 'broadcastQueue')) {
            $queue = $event->broadcastQueue();
        } elseif (isset($event->broadcastQueue)) {
            $queue = $event->broadcastQueue;
        } elseif (isset($event->queue)) {
            $queue = $event->queue;
        }

        CQueue::queuer()->connection(isset($event->connection) ? $event->connection : null)->pushOn(
            $queue,
            new CBroadcast_BroadcastEvent(clone $event)
        );
    }

    /**
     * Get a driver instance.
     *
     * @param null|string $driver
     *
     * @return mixed
     */
    public function connection($driver = null) {
        return $this->driver($driver);
    }

    /**
     * Get a driver instance.
     *
     * @param null|string $name
     *
     * @return CBroadcast_BroadcasterAbstract
     */
    public function driver($name = null) {
        $name = $name ?: $this->getDefaultDriver();

        return $this->drivers[$name] = $this->get($name);
    }

    /**
     * Attempt to get the connection from the local cache.
     *
     * @param string $name
     *
     * @return \CBroadcast_Contract_BroadcasterInterface
     */
    protected function get($name) {
        return isset($this->drivers[$name]) ? $this->drivers[$name] : $this->resolve($name);
    }

    /**
     * Resolve the given broadcaster.
     *
     * @param string $name
     *
     * @throws \InvalidArgumentException
     *
     * @return \CBroadcast_Contract_BroadcasterInterface
     */
    protected function resolve($name) {
        $config = $this->getConfig($name);

        if (isset($this->customCreators[$config['driver']])) {
            return $this->callCustomCreator($config);
        }

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (!method_exists($this, $driverMethod)) {
            throw new InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
        }

        return $this->{$driverMethod}($config);
    }

    /**
     * Call a custom driver creator.
     *
     * @param array $config
     *
     * @return mixed
     */
    protected function callCustomCreator(array $config) {
        return $this->customCreators[$config['driver']]($config);
    }

    /**
     * Create an instance of the driver.
     *
     * @param array $config
     *
     * @return \CBroadcast_Contract_BroadcasterInterface
     */
    protected function createPusherDriver(array $config) {
        $options = $config['options'] ? $config['options'] : [];

        $guzzleOptions = [
            'connect_timeout' => carr::get($options, 'connect_timeout', 5),
            'timeout' => carr::get($options, 'timeout', 30),
        ];
        if (array_key_exists('verify', $options)) {
            $guzzleOptions['verify'] = $options['verify'];
        }
        $client = new \GuzzleHttp\Client($guzzleOptions);

        $pusher = new Pusher(
            $config['key'],
            $config['secret'],
            $config['app_id'],
            $options,
            $client
        );

        $log = isset($config['log']) ? $config['log'] : false;
        if ($log) {
            $pusher->setLogger(CLogger::logger());
        }

        return new CBroadcast_Broadcaster_PusherBroadcaster($pusher);
    }

    /**
     * Create an instance of the driver.
     *
     * @param array $config
     *
     * @return \CBroadcast_Contract_BroadcasterInterface
     */
    protected function createAblyDriver(array $config) {
        return new CBroadcast_Broadcaster_AblyBroadcaster(new AblyRest($config));
    }

    /**
     * Create an instance of the driver.
     *
     * @param array $config
     *
     * @return \CBroadcast_Contract_BroadcasterInterface
     */
    protected function createRedisDriver(array $config) {
        return new CBroadcast_Broadcaster_RedisBroadcaster(
            CRedis::instance(),
            isset($config['connection']) ? $config['connection'] : null,
            CF::config('database.redis.options.prefix', '')
        );
    }

    /**
     * Create an instance of the driver.
     *
     * @param array $config
     *
     * @return \CBroadcast_Contract_BroadcasterInterface
     */
    protected function createSseDriver(array $config) {
        $eventHistory = new CBroadcast_SSE_Storage_BroadcastEventHistoryCached();

        return new CBroadcast_Broadcaster_RedisSseBroadcaster(
            $eventHistory,
            CRedis::instance(),
            isset($config['connection']) ? $config['connection'] : null,
            CF::config('database.redis.options.prefix', '')
        );
    }

    /**
     * Create an instance of the driver.
     *
     * @param array $config
     *
     * @return \CBroadcast_Contract_BroadcasterInterface
     */
    protected function createLogDriver(array $config) {
        return new CBroadcast_Broadcaster_LogBroadcaster(
            CLogger::logger()
        );
    }

    /**
     * Create an instance of the driver.
     *
     * @param array $config
     *
     * @return \CBroadcast_Contract_BroadcasterInterface
     */
    protected function createNullDriver(array $config) {
        return new CBroadcast_Broadcaster_NullBroadcaster();
    }

    /**
     * Get the connection configuration.
     *
     * @param string $name
     *
     * @return array
     */
    protected function getConfig($name) {
        if (!is_null($name) && $name !== 'null') {
            return CF::config("broadcast.connections.{$name}");
        }

        return ['driver' => 'null'];
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver() {
        return CF::config('broadcast.default');
    }

    /**
     * Disconnect the given disk and remove from local cache.
     *
     * @param null|string $name
     *
     * @return void
     */
    public function purge($name = null) {
        $name = $name ?: $this->getDefaultDriver();

        unset($this->drivers[$name]);
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param string   $driver
     * @param \Closure $callback
     *
     * @return $this
     */
    public function extend($driver, Closure $callback) {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Forget all of the resolved driver instances.
     *
     * @return $this
     */
    public function forgetDrivers() {
        $this->drivers = [];

        return $this;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters) {
        return $this->driver()->$method(...$parameters);
    }
}
