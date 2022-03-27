<?php

/**
 * @author Hery Kurniawan
 */
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

    /**
     * @var CLogger Singleton instance container
     */
    private static $instance = null;

    private function __construct() {
        $options['path'] = 'system';
        $this->threshold = CF::config('log.threshold', static::DEBUG);
        $this->createWriter('file', $options);
    }

    /**
     * @return CLogger
     */
    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new CLogger();
            register_shutdown_function([CLogger::$instance, 'write']);
        }

        return self::$instance;
    }

    /**
     * Create a log writer, and optionally limits the levels of messages that
     * will be written by the writer.
     *
     *     $log->create_write('file');
     *
     * @param type    $type    string
     * @param options $options array of options for writers
     *
     * @return CLogger
     */
    public function createWriter($type = 'file', $options = []) {
        $levels = carr::get($options, 'levels', []);
        $min_level = carr::get($options, 'min_level', 0);
        if (!is_array($levels)) {
            $levels = range($min_level, $levels);
        }

        $writer = CLogger_Writer::factory($type, $options);
        $this->_writers["{$writer}"] = [
            'object' => $writer,
            'levels' => $levels,
        ];

        return $this;
    }

    /**
     * Adds a message to the log. Replacement values must be passed in to be
     * replaced using [strtr](http://php.net/strtr).
     *
     *     $log->add(Log::ERROR, 'Could not locate user: :user', array(
     *         ':user' => $username,
     *     ));
     *
     * @param string    $level     level of message
     * @param string    $message   message body
     * @param array     $values    values to replace in the message
     * @param array     $context   additional custom parameters to supply to the log writer
     * @param Exception $exception Exception for log
     *
     * @return Log
     */
    public function add($level, $message, array $values = null, array $context = [], $exception = null) {
        if (is_string($level)) {
            $level = carr::get(self::$logLevels, $level);
        }
        if (!is_numeric($level)) {
            $level = static::EMERGENCY;
        }

        if ($level >= $this->threshold) {
            return;
        }

        if ($values) {
            // Insert the values into the message
            $message = strtr($message, $values);
        }

        if (strlen($message) == 0 && $exception != null) {
            $message = get_class($exception);
        }

        $trace = [];
        // Grab a copy of the trace
        if ($exception != null) {
            $trace = $exception->getTrace();
        } else {
            // Older php version don't have 'DEBUG_BACKTRACE_IGNORE_ARGS', so manually remove the args from the backtrace
            if (!defined('DEBUG_BACKTRACE_IGNORE_ARGS')) {
                $trace = array_map(function ($item) {
                    unset($item['args']);

                    return $item;
                }, array_slice(debug_backtrace(false), 1));
            } else {
                $trace = array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 1);
            }
        }

        // Create a new message
        $this->messages[] = [
            'time' => time(),
            'level' => $level,
            'body' => $message,
            'trace' => $trace,
            'domain' => CF::domain(),
            'file' => isset($trace[0]['file']) ? $trace[0]['file'] : null,
            'line' => isset($trace[0]['line']) ? $trace[0]['line'] : null,
            'class' => isset($trace[0]['class']) ? $trace[0]['class'] : null,
            'function' => isset($trace[0]['function']) ? $trace[0]['function'] : null,
            'context' => $context,
            'exception' => $exception,
        ];
        if (CLogger::$writeOnAdd) {
            // Write logs as they are added
            $this->write();
        }

        return $this;
    }

    /**
     * Write and clear all of the messages.
     *
     *     $log->write();
     *
     * @return void
     */
    public function write() {
        if (empty($this->messages)) {
            // There is nothing to write, move along
            return;
        }

        // Import all messages locally
        $messages = $this->messages;

        // Reset the messages array
        $this->messages = [];

        foreach ($this->_writers as $writer) {
            if (empty($writer['levels'])) {
                // Write all of the messages
                $writer['object']->write($messages);
            } else {
                // Filtered messages
                $filtered = [];

                foreach ($messages as $message) {
                    if (in_array($message['level'], $writer['levels'])) {
                        // Writer accepts this kind of message
                        $filtered[] = $message;
                    }
                }

                // Write the filtered messages
                $writer['object']->write($filtered);
            }
        }
    }

    public static function getLevels() {
        return static::$logLevels;
    }

    public static function logger() {
        return CLogger_Manager::instance();
    }
}
