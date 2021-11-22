<?php
use Monolog\Logger as Monolog;

trait CLogger_Concern_ParseLogConfigurationTrait {
    /**
     * The Log levels.
     *
     * @var array
     */
    protected $levels = [
        'debug' => Monolog::DEBUG,
        'info' => Monolog::INFO,
        'notice' => Monolog::NOTICE,
        'warning' => Monolog::WARNING,
        'error' => Monolog::ERROR,
        'critical' => Monolog::CRITICAL,
        'alert' => Monolog::ALERT,
        'emergency' => Monolog::EMERGENCY,
    ];

    /**
     * Get fallback log channel name.
     *
     * @return string
     */
    abstract protected function getFallbackChannelName();

    /**
     * Parse the string level into a Monolog constant.
     *
     * @param array $config
     *
     * @throws \InvalidArgumentException
     *
     * @return int
     */
    protected function level(array $config) {
        $level = $config['level'] ?? 'debug';

        if (isset($this->levels[$level])) {
            return $this->levels[$level];
        }

        throw new InvalidArgumentException('Invalid log level.');
    }

    /**
     * Extract the log channel from the given configuration.
     *
     * @param array $config
     *
     * @return string
     */
    protected function parseChannel(array $config) {
        return isset($config['name']) ? $config['name'] : $this->getFallbackChannelName();
    }
}
