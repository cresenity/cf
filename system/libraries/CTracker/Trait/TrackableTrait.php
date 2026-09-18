<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 11:26:11 PM
 */
use CHelper_IpAddress as IpAddress;

trait CTracker_Trait_TrackableTrait {
    protected function isTrackable() {
        return $this->config->isTrackEnabled()
                && $this->logIsEnabled()
                && $this->allowConsole()
                && $this->parserIsAvailable()
                && $this->isTrackableIp()
                && $this->isTrackableEnvironment()
                //$this->routeIsTrackable() &&
                && $this->pathIsTrackable()
                && $this->notRobotOrTrackable();
    }

    protected function logIsEnabled() {
        $enabled = $this->config->get('logEnabled')
                || $this->config->get('logSqlQuery')
                || $this->config->get('logSqlQueryBinding')
                || $this->config->get('logEvent')
                || $this->config->get('logGeoIp')
                || $this->config->get('logAgent')
                || $this->config->get('logUser')
                || $this->config->get('logDevices')
                || $this->config->get('logLanguage')
                || $this->config->get('logReferer')
                || $this->config->get('logPath')
                || $this->config->get('logQuery')
                || $this->config->get('logRoute')
                || $this->config->get('logError');
        if (!$enabled) {
            $this->logUntrackable('there are no log items enabled.');
        }

        return $enabled;
    }

    private function logUntrackable($item) {
        if ($this->config->isLogUntrackable() && !isset($this->loggedItems[$item])) {
            $this->getLogger()->warning('TRACKER (unable to track item): ' . $item);
            $this->loggedItems[$item] = $item;
        }
    }

    public function allowConsole() {
        return (!$this->isRunningInConsole()) || $this->config->isConsoleEnabled();
    }

    public function isRunningInConsole() {
        return PHP_SAPI === 'cli';
    }

    public function parserIsAvailable() {
        if (!$this->repositoryManager->parserIsAvailable()) {
            $this->logger->error(c::__('tracker.regex_file_not_available'));

            return false;
        }

        return true;
    }

    protected function isTrackableIp() {
        $clientIp = CTracker::populator()->get('request.clientIp');
        if (strpos($clientIp, ',') !== false) {
            $clientIp = trim(carr::get(explode(',', $clientIp), 0));
        }
        $trackable = !IpAddress::ipv4InRange(
            $ipAddress = $clientIp,
            $this->config->get('excludeIpAddress')
        );
        if (!$trackable) {
            $this->logUntrackable($ipAddress . ' is not trackable.');
        }

        return $trackable;
    }

    protected function isTrackableEnvironment() {
        $trackable = !in_array(CApp_Base::environment(), $this->config->getExcludeEnvironment());
        if (!$trackable) {
            $this->logUntrackable('environment ' . CApp_Base::environment() . ' is not trackable.');
        }

        return $trackable;
    }

    private function routeIsTrackable() {
        if (!$this->route) {
            return false;
        }
        if (!$trackable = $this->repositoryManager->routeIsTrackable($this->route)) {
            $this->logUntrackable('route ' . $this->route . ' is not trackable.');
        }

        return $trackable;
    }

    private function pathIsTrackable() {
        if (!$trackable = $this->repositoryManager->pathIsTrackable(CTracker::populator()->get('request.path'))) {
            $this->logUntrackable('path ' . CTracker::populator()->get('request.path') . ' is not trackable.');
        }

        return $trackable;
    }

    protected function notRobotOrTrackable() {
        $trackable = !$this->isRobot()
                || $this->config->isRobotEnabled();
        if (!$trackable) {
            $this->logUntrackable('tracking of robots is disabled.');
        }

        return $trackable;
    }
}
