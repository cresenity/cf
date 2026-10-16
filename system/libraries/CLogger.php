<?php

class CLogger {
    const __EXT = '.log';

    // Log message levels - Windows users see PHP Bug #18090
    const EMERGENCY = LOG_EMERG;    // 0

    const ALERT = LOG_ALERT;    // 1

    const CRITICAL = LOG_CRIT;     // 2

    const ERROR = LOG_ERR;      // 3

    const WARNING = LOG_WARNING;  // 4

    const NOTICE = LOG_NOTICE;   // 5

    const INFO = LOG_INFO;     // 6

    const DEBUG = LOG_DEBUG;    // 7

    protected static $logLevels = [
        'emergency' => self::EMERGENCY,
        'alert' => self::ALERT,
        'critical' => self::CRITICAL,
        'error' => self::ERROR,
        'warning' => self::WARNING,
        'notice' => self::NOTICE,
        'info' => self::INFO,
        'debug' => self::DEBUG,
    ];

    /**
     * @var array list of added messages
     */
    protected $messages = [];

    protected static $writeOnAdd = false;

    protected $threshold;

    protected $writers = [];

    /**
     * @var CLogger Singleton instance container
     */
    private static $instance = null;

    private function __construct() {
        $options['path'] = 'system';
        $this->threshold = CF::config('log.threshold', static::DEBUG);
    }

    /**
     * @return CLogger
     */
    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new CLogger();
        }

        return self::$instance;
    }

    public static function getLevels() {
        return static::$logLevels;
    }

    /**
     * @return CLogger_Manager
     */
    public static function logger() {
        return CLogger_Manager::instance();
    }

    /**
     * Get a log channel instance.
     *
     * @param null|string $channel
     *
     * @return \Psr\Log\LoggerInterface
     */
    public static function channel($channel = null) {
        return CLogger_Manager::instance()->channel($channel);
    }

    /**
     * System is unusable.
     *
     * @param string $message
     *
     * @return void
     */
    public static function emergency($message, array $context = []) {
        self::channel()->emergency($message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     *
     * @return void
     */
    public static function alert($message, array $context = []) {
        self::channel()->alert($message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     *
     * @return void
     */
    public static function critical($message, array $context = []) {
        self::channel()->critical($message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     *
     * @return void
     */
    public static function error($message, array $context = []) {
        self::channel()->error($message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     *
     * @return void
     */
    public static function warning($message, array $context = []) {
        self::channel()->warning($message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     *
     * @return void
     */
    public static function notice($message, array $context = []) {
        self::channel()->notice($message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     *
     * @return void
     */
    public static function info($message, array $context = []) {
        self::channel()->info($message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     *
     * @return void
     */
    public static function debug($message, array $context = []) {
        self::channel()->debug($message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     *
     * @throws InvalidArgumentException level int yang tidak dikenal
     *
     * @return void
     */
    public static function log($level, $message, array $context = []) {
        if (!is_string($level)) {
            $levelMap = [
                CLogger::EMERGENCY => 'emergency',
                CLogger::ALERT => 'alert',
                CLogger::CRITICAL => 'critical',
                CLogger::ERROR => 'error',
                CLogger::WARNING => 'warning',
                CLogger::NOTICE => 'notice',
                CLogger::INFO => 'info',
                CLogger::DEBUG => 'debug',
            ];
            if (!isset($levelMap[$level])) {
                throw new InvalidArgumentException('Invalid log level: ' . $level);
            }
            $level = $levelMap[$level];
        }
        self::channel()->log($level, $message, $context);
    }

    /**
     * @return CLogger_Reader
     */
    public static function reader() {
        return CLogger_Reader::instance();
    }
}
