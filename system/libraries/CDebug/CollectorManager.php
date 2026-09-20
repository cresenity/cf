<?php

class CDebug_CollectorManager {
    private static $instance;

    /**
     * @var CDebug_Collector_Exception
     */
    private $exception;

    /**
     * @var CDebug_Collector_Deprecated
     */
    private $deprecated;

    public static function instance() {
        if (static::$instance == null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * @return CDebug_Collector_Exception
     */
    public function exception() {
        if ($this->exception == null) {
            $this->exception = new CDebug_Collector_Exception();
        }

        return $this->exception;
    }

    /**
     * @return CDebug_Collector_Deprecated
     */
    public function deprecated() {
        if ($this->deprecated == null) {
            $this->deprecated = new CDebug_Collector_Deprecated();
        }

        return $this->deprecated;
    }

    public static function allCollectorType() {
        return [CDebug::COLLECTOR_TYPE_DEPRECATED, CDebug::COLLECTOR_TYPE_EXCEPTION, CDebug::COLLECTOR_TYPE_PROFILER];
    }

    /**
     * @param Throwable $ex
     *
     * @return void
     */
    public function collectException(Throwable $ex) {
        return $this->exception()->collect($ex);
    }

    /**
     * @param string $message
     * @param array  $context ['api', 'replacement', 'since'] — lihat CDebug_Collector_Deprecated::collect()
     *
     * @return null|array
     */
    public function collectDeprecated($message = '', array $context = []) {
        return $this->deprecated()->collect($message, $context);
    }
}
