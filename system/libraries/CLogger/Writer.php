<?php
/**
 * @see CLogger
 * @deprecated 1.9 warisan Kohana, tidak dipakai CLogger; tulis log lewat CLogger::channel(). Dihapus di 2.0
 */
abstract class CLogger_Writer {
    /**
     * @var string timestamp format for log entries.
     *
     * Defaults to Date::$timestamp_format
     */
    public static $timestamp;

    /**
     * @var string timezone for log entries
     *
     * Defaults to Date::$timezone, which defaults to date_default_timezone_get()
     */
    public static $timezone;

    /**
     * @var int Level to use for stack traces
     */
    public static $straceLevel = LOG_DEBUG;

    /**
     * Numeric log level to string lookup table.
     *
     * @var array
     */
    protected $logLevels = [
        LOG_EMERG => 'EMERGENCY',
        LOG_ALERT => 'ALERT',
        LOG_CRIT => 'CRITICAL',
        LOG_ERR => 'ERROR',
        LOG_WARNING => 'WARNING',
        LOG_NOTICE => 'NOTICE',
        LOG_INFO => 'INFO',
        LOG_DEBUG => 'DEBUG',
    ];

    public static function factory($type = 'file', $options = []) {
        switch ($type) {
            case 'file':
                return new CLogger_Writer_File($options);

                break;
        }

        return new CLogger_Writer_File($options);
    }

    /**
     * Write an array of messages.
     *
     *     $writer->write($messages);
     *
     * @return void
     */
    abstract public function write(array $messages);

    /**
     * Allows the writer to have a unique key when stored.
     *
     *     echo $writer;
     *
     * @return string
     */
    final public function __toString() {
        return spl_object_hash($this);
    }

    /**
     * Formats a log entry.
     *
     * @param string $format
     *
     * @return string
     */
    public function formatMessage(array $message, $format = 'time --- domain:level: body in file:line') {
        $message['time'] = date('Y-m-d H:i:s', $message['time']);
        $message['level'] = $this->logLevels[$message['level']];

        $string = strtr($format, array_filter($message, 'is_scalar'));

        if (isset($message['exception']) && $message['exception'] != null) {
            // Re-use as much as possible, just resetting the body to the trace

            if (carr::get($message, 'level') >= static::$straceLevel) {
                if (is_array($message['trace'])) {
                    $message['body'] .= json_encode($message['trace']);
                } else {
                    $message['body'] .= $message['trace'];
                }
            }
            if (isset($this->logLevels[$message['level']])) {
                $message['level'] = $this->logLevels[$message['level']];
            }

            $string .= PHP_EOL . strtr($format, array_filter($message, 'is_scalar'));
        }

        return $string;
    }
}
