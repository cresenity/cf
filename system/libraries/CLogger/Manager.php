<?php

use Psr\Log\LoggerInterface;
use Monolog\Logger as Monolog;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SlackWebhookHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Handler\FormattableHandlerInterface;

class CLogger_Manager implements LoggerInterface {
    use CLogger_Concern_ParseLogConfigurationTrait;

    /**
     * The array of resolved channels.
     *
     * @var array
     */
    protected $channels = [];

    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $customCreators = [];

    /**
     * The standard date format to use when writing logs.
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    private static $instance;

    /**
     * @return CLogger_Manager
     */
    public static function instance() {
        if (static::$instance == null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    private function __construct() {
    }

    /**
     * Build an on-demand log channel.
     *
     * @param array $config
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function build(array $config) {
        unset($this->channels['ondemand']);

        return $this->get('ondemand', $config);
    }

    /**
     * Create a new, on-demand aggregate logger instance.
     *
     * @param array       $channels
     * @param null|string $channel
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function stack(array $channels, $channel = null) {
        return new CLogger_Logger(
            $this->createStackDriver(compact('channels', 'channel')),
            CEvent::dispatcher()
        );
    }

    /**
     * Get a log channel instance.
     *
     * @param null|string $channel
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function channel($channel = null) {
        return $this->driver($channel);
    }

    /**
     * Get a log driver instance.
     *
     * @param null|string $driver
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function driver($driver = null) {
        return $this->get($this->parseDriver($driver));
    }

    /**
     * @return array
     */
    public function getChannels() {
        return $this->channels;
    }

    /**
     * Attempt to get the log from the local cache.
     *
     * @param string     $name
     * @param null|array $config
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function get($name, array $config = null) {
        if (isset($this->channels[$name])) {
            return $this->channels[$name];
        }

        try {
            return c::with($this->resolve($name, $config), function ($logger) use ($name) {
                return $this->channels[$name] = $this->tap($name, new CLogger_Logger($logger, CEvent::dispatcher()));
            });
        } catch (Throwable $e) {
            return c::tap($this->createEmergencyLogger(), function ($logger) use ($e) {
                $logger->emergency('Unable to create configured logger. Using emergency logger.', [
                    'exception' => $e,
                ]);
            });
        } catch (Exception $e) {
            return c::tap($this->createEmergencyLogger(), function ($logger) use ($e) {
                $logger->emergency('Unable to create configured logger. Using emergency logger.', [
                    'exception' => $e,
                ]);
            });
        }
    }

    /**
     * Apply the configured taps for the logger.
     *
     * @param string          $name
     * @param \CLogger_Logger $logger
     *
     * @return \CLogger_Logger
     */
    protected function tap($name, CLogger_Logger $logger) {
        $taps = isset($this->configurationFor($name)['tap']) ? $this->configurationFor($name)['tap'] : [];
        foreach ($taps as $tap) {
            list($class, $arguments) = $this->parseTap($tap);

            c::container($class)->__invoke($logger, ...explode(',', $arguments));
        }

        return $logger;
    }

    /**
     * Parse the given tap class string into a class name and arguments string.
     *
     * @param string $tap
     *
     * @return array
     */
    protected function parseTap($tap) {
        return cstr::contains($tap, ':') ? explode(':', $tap, 2) : [$tap, ''];
    }

    /**
     * Create an emergency log handler to avoid white screens of death.
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function createEmergencyLogger() {
        $config = $this->configurationFor('emergency');

        $handler = new StreamHandler(
            isset($config['path']) ? $config['path'] : DOCROOT . '/logs/cf.log',
            $this->level(['level' => 'debug'])
        );

        return new CLogger_Logger(
            new Monolog('cf', $this->prepareHandlers([$handler])),
            CEvent::dispatcher()
        );
    }

    /**
     * Resolve the given log instance by name.
     *
     * @param string     $name
     * @param null|array $config
     *
     * @throws \InvalidArgumentException
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function resolve($name, array $config = null) {
        $config = $config ?: $this->configurationFor($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Log [{$name}] is not defined.");
        }

        if (isset($this->customCreators[$config['driver']])) {
            return $this->callCustomCreator($config);
        }
        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($config);
        }

        throw new InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
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
     * Create a custom log driver instance.
     *
     * @param array $config
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function createCustomDriver(array $config) {
        $factory = is_callable($via = $config['via']) ? $via : c::container($via);

        return $factory($config);
    }

    /**
     * Create an aggregate log driver instance.
     *
     * @param array $config
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function createStackDriver(array $config) {
        if (is_string($config['channels'])) {
            $config['channels'] = explode(',', $config['channels']);
        }

        $handlers = c::collect($config['channels'])->flatMap(function ($channel) {
            return $channel instanceof LoggerInterface
                ? $channel->getHandlers()
                : $this->channel($channel)->getHandlers();
        })->all();

        $processors = c::collect($config['channels'])->flatMap(function ($channel) {
            return $channel instanceof LoggerInterface
                ? $channel->getProcessors()
                : $this->channel($channel)->getProcessors();
        })->all();

        if ((isset($config['ignore_exceptions']) ? $config['ignore_exceptions'] : false)) {
            $handlers = [new WhatFailureGroupHandler($handlers)];
        }

        return new Monolog($this->parseChannel($config), $handlers, $processors);
    }

    /**
     * Create an instance of the single file log driver.
     *
     * @param array $config
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function createSingleDriver(array $config) {
        return new Monolog($this->parseChannel($config), [
            $this->prepareHandler(
                new StreamHandler(
                    $config['path'],
                    $this->level($config),
                    isset($config['bubble']) ? $config['bubble'] : true,
                    isset($config['permission']) ? $config['permission'] : null,
                    isset($config['locking']) ? $config['locking'] : false
                ),
                $config
            ),
        ]);
    }

    /**
     * Create an instance of the daily file log driver.
     *
     * @param array $config
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function createDailyDriver(array $config) {
        return new Monolog($this->parseChannel($config), [
            $this->prepareHandler(new RotatingFileHandler(
                $config['path'],
                isset($config['days']) ? $config['days'] : 7,
                $this->level($config),
                isset($config['bubble']) ? $config['bubble'] : true,
                isset($config['permission']) ? $config['permission'] : null,
                isset($config['locking']) ? $config['locking'] : false
            ), $config),
        ]);
    }

    /**
     * Create an instance of the Slack log driver.
     *
     * @param array $config
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function createSlackDriver(array $config) {
        return new Monolog($this->parseChannel($config), [
            $this->prepareHandler(new SlackWebhookHandler(
                $config['url'],
                isset($config['channel']) ? $config['channel'] : null,
                isset($config['username']) ? $config['username'] : 'CF',
                isset($config['attachment']) ? $config['attachment'] : true,
                isset($config['emoji']) ? $config['emoji'] : ':boom:',
                isset($config['short']) ? $config['short'] : false,
                isset($config['context']) ? $config['context'] : true,
                $this->level($config),
                isset($config['bubble']) ? $config['bubble'] : true,
                isset($config['exclude_fields']) ? $config['exclude_fields'] : []
            ), $config),
        ]);
    }

    /**
     * Create an instance of the syslog log driver.
     *
     * @param array $config
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function createSyslogDriver(array $config) {
        return new Monolog($this->parseChannel($config), [
            $this->prepareHandler(new SyslogHandler(
                cstr::snake(CF::config('app.name'), '-'),
                isset($config['facility']) ? $config['facility'] : LOG_USER,
                $this->level($config)
            ), $config),
        ]);
    }

    /**
     * Create an instance of the "error log" log driver.
     *
     * @param array $config
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function createErrorlogDriver(array $config) {
        return new Monolog($this->parseChannel($config), [
            $this->prepareHandler(new ErrorLogHandler(
                isset($config['type']) ? $config['type'] : ErrorLogHandler::OPERATING_SYSTEM,
                $this->level($config)
            )),
        ]);
    }

    /**
     * Create an instance of any handler available in Monolog.
     *
     * @param array $config
     *
     * @throws \InvalidArgumentException
     * @throws \CContainer_Exception_BindingResolutionException
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function createMonologDriver(array $config) {
        if (!is_a($config['handler'], HandlerInterface::class, true)) {
            throw new InvalidArgumentException(
                $config['handler'] . ' must be an instance of ' . HandlerInterface::class
            );
        }

        $with = array_merge(
            ['level' => $this->level($config)],
            isset($config['with']) ? $config['with'] : [],
            isset($config['handler_with']) ? $config['handler_with'] : []
        );

        return new Monolog($this->parseChannel($config), [$this->prepareHandler(
            $this->app->make($config['handler'], $with),
            $config
        )]);
    }

    /**
     * Prepare the handlers for usage by Monolog.
     *
     * @param array $handlers
     *
     * @return array
     */
    protected function prepareHandlers(array $handlers) {
        foreach ($handlers as $key => $handler) {
            $handlers[$key] = $this->prepareHandler($handler);
        }

        return $handlers;
    }

    /**
     * Prepare the handler for usage by Monolog.
     *
     * @param \Monolog\Handler\HandlerInterface $handler
     * @param array                             $config
     *
     * @return \Monolog\Handler\HandlerInterface
     */
    protected function prepareHandler(HandlerInterface $handler, array $config = []) {
        if (Monolog::API !== 1 && (Monolog::API !== 2 || !$handler instanceof FormattableHandlerInterface)) {
            return $handler;
        }

        if (!isset($config['formatter'])) {
            $handler->setFormatter($this->formatter());
        } elseif ($config['formatter'] !== 'default') {
            $handler->setFormatter(c::container()->make($config['formatter'], isset($config['formatter_with']) ? $config['formatter_with'] : []));
        }

        return $handler;
    }

    /**
     * Get a Monolog formatter instance.
     *
     * @return \Monolog\Formatter\FormatterInterface
     */
    protected function formatter() {
        return c::tap(new LineFormatter(null, $this->dateFormat, true, true), function ($formatter) {
            $formatter->includeStacktraces();
        });
    }

    /**
     * Get fallback log channel name.
     *
     * @return string
     */
    protected function getFallbackChannelName() {
        return c::environment();
    }

    /**
     * Get the log connection configuration.
     *
     * @param string $name
     *
     * @return array
     */
    protected function configurationFor($name) {
        $config = CF::config("log.channels.{$name}");
        $driver = carr::get($config, 'driver');
        if (in_array($driver, ['single', 'daily']) && carr::get($config, 'path') == null) {
            // Set the yearly directory name
            $date = date('Y-m');
            list($year, $month) = explode('-', $date);
            $config['path'] = DOCROOT . 'logs' . DS . CF::appCode() . DS . $year . DS . $month . DS . 'log.log';
        }

        return $config;
    }

    /**
     * Get the default log driver name.
     *
     * @return null|string
     */
    public function getDefaultDriver() {
        return CF::config('log.default');
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
        $this->customCreators[$driver] = $callback->bindTo($this, $this);

        return $this;
    }

    /**
     * Unset the given channel instance.
     *
     * @param null|string $driver
     *
     * @return $this
     */
    public function forgetChannel($driver = null) {
        $driver = $this->parseDriver($driver);

        if (isset($this->channels[$driver])) {
            unset($this->channels[$driver]);
        }
    }

    /**
     * Parse the driver name.
     *
     * @param null|string $driver
     *
     * @return null|string
     */
    protected function parseDriver($driver) {
        $driver = $driver ?: $this->getDefaultDriver();

        if (CF::isTesting()) {
            $driver = $driver ?: 'null';
        }

        return $driver;
    }

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function emergency($message, array $context = []) {
        $this->driver()->emergency($message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function alert($message, array $context = []) {
        $this->driver()->alert($message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function critical($message, array $context = []) {
        $this->driver()->critical($message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function error($message, array $context = []) {
        $this->driver()->error($message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function warning($message, array $context = []) {
        $this->driver()->warning($message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function notice($message, array $context = []) {
        $this->driver()->notice($message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function info($message, array $context = []) {
        $this->driver()->info($message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function debug($message, array $context = []) {
        $this->driver()->debug($message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function log($level, $message, array $context = []) {
        $this->driver()->log($level, $message, $context);
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
