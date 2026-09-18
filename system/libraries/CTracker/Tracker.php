<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 11:16:30 PM
 */
use Psr\Log\NullLogger;
use MongoDB\BSON\ObjectID;

class CTracker_Tracker {
    use CTracker_Trait_TrackableTrait;

    /**
     * @var CRouting_Router
     */
    protected $route;

    /**
     * @var CTracker_RepositoryManager
     */
    protected $repositoryManager;

    /**
     * @var array
     */
    protected $sessionData;

    /**
     * @var CTracker_Config
     */
    protected $config;

    private $booted = false;

    public function __construct() {
        $this->repositoryManager = CTracker_RepositoryManager::instance();
        $this->config = CTracker_Config::instance();
        $this->route = CTracker::populator()->get('route');
        $this->logger = $this->config->getLogger() ? $this->config->getLogger() : new NullLogger();
        $this->sessionData = CTracker::populator()->get('session');
    }

    public function getSessionId($updateLastActivity = false) {
        return $this->repositoryManager->getSessionId(
            $this->makeSessionData(),
            $updateLastActivity
        );
    }

    public function getUserId() {
        return $this->repositoryManager->getCurrentUserId();
    }

    public function getDeviceId() {
        return $this->config->isLogDevice() ? $this->repositoryManager->findOrCreateDevice(
            $this->repositoryManager->getCurrentDeviceProperties()
        ) : null;
    }

    public function getCookieId() {
        return $this->config->isLogCookie() ? $this->repositoryManager->getCookieId() : null;
    }

    public function getLanguageId() {
        return $this->config->isLoglanguage() ? $this->repositoryManager->findOrCreateLanguage($this->repositoryManager->getCurrentLanguage()) : null;
    }

    public function getPathId() {
        return $this->config->isLogPath() ? $this->repositoryManager->findOrCreatePath(['path' => CTracker::populator()->get('request.path'), ]) : null;
    }

    public function getQueryId() {
        if ($this->config->isLogQuery()) {
            if (count($arguments = CTracker::populator()->get('request.query'))) {
                return $this->repositoryManager->getQueryId(
                    [
                        'query' => carr::implode('=', '|', $arguments),
                        'arguments' => $arguments,
                    ]
                );
            }
        }
    }

    public function isRobot() {
        return $this->repositoryManager->isRobot();
    }

    public function logSqlQuery($query, $bindings, $time, $name) {
        if ($this->isTrackable()
            && $this->config->isLogEnabled()
            && $this->config->isLogSqlQuery()
            && $this->isSqlQueriesLoggableConnection($name)
        ) {
            $this->repositoryManager->logSqlQuery($query, $bindings, $time, $name);
        }
    }

    public function boot() {
        if ($this->booted) {
            return false;
        }

        $this->booted = true;
        if ($this->isTrackable()) {
            if (CTracker::config()->get('isQueued')) {
                $queueData = [
                    'data' => CTracker::populator()->getData(),
                    'config' => CTracker::config()->getData(),
                ];
                $trackQueueClass = CTracker::config()->get('trackQueueClass');

                return $trackQueueClass::dispatch($queueData)->allOnConnection(CTracker::config()->get('queueConnection'));
            }

            $this->track();
        }
    }

    public function track() {
        $log = $this->getLogData();

        if ($this->config->isLogEnabled()) {
            $this->repositoryManager->createLog($log);
        }
    }

    /**
     * @return array
     */
    protected function getLogData() {
        $logData = [
            'log_session_id' => $this->getSessionId(true),
            'method' => CTracker::populator()->get('request.method'),
            'log_path_id' => $this->getPathId(),
            'log_query_id' => $this->getQueryId(),
            'log_referer_id' => $this->getRefererId(),
            'is_ajax' => CTracker::populator()->get('request.isAjax'),
            'is_secure' => CTracker::populator()->get('request.isSecure'),
            'is_json' => CTracker::populator()->get('request.isJson'),
            'wants_json' => CTracker::populator()->get('request.wantsJson'),
            'updated' => CTracker::populator()->get('session.updated'),
            //make log created with current session updated
            'created' => CTracker::populator()->get('session.updated'),
        ];
        $customLogData = CTracker::populator()->get('customLogData');
        if (is_array($customLogData)) {
            $logData = array_merge($logData, $customLogData);
        }

        return $logData;
    }

    /**
     * @return array
     */
    protected function makeSessionData() {
        //$logDeviceId = new ObjectID($this->getDeviceId());
        $clientIp = CTracker::populator()->get('request.clientIp');
        if (strpos($clientIp, ',') !== false) {
            $clientIp = trim(carr::get(explode(',', $clientIp), 0));
        }
        $sessionData = [
            'user_id' => $this->getUserId(),
            'log_device_id' => $this->getDeviceId(),
            'client_ip' => $clientIp,
            'uuid' => CTracker::populator()->get('session.uuid'),
            'log_geoip_id' => $this->getGeoIpId(),
            'log_agent_id' => $this->getAgentId(),
            'log_referer_id' => $this->getRefererId(),
            'log_cookie_id' => $this->getCookieId(),
            'log_language_id' => $this->getLanguageId(),
            'is_robot' => $this->isRobot(),
            'updated' => CTracker::populator()->get('session.updated'),
            'created' => CTracker::populator()->get('session.created'),
            // The key user_agent is not present in the sessions table, but
            // it's internally used to check if the user agent changed
            // during a session.
            'user_agent' => CTracker::populator()->get('request.userAgent'),
            'active_second' => CTracker::populator()->get('session.activeSecond'),
        ];

        $customSessionData = CTracker::populator()->get('customSessionData');
        if (is_array($customSessionData)) {
            $sessionData = array_merge($sessionData, $customSessionData);
        }

        $this->sessionData = $sessionData;

        return $this->sessionData;
    }

    protected function getGeoIpId() {
        return $this->config->isLogGeoIp() ? $this->repositoryManager->getGeoIpId(CTracker::populator()->get('request.clientIp')) : null;
    }

    protected function getAgentId() {
        return $this->config->isLogAgent() ? $this->repositoryManager->getAgentId() : null;
    }

    protected function getRefererId() {
        return $this->config->isLogReferer() ? $this->repositoryManager->getRefererId(
            CTracker::populator()->get('request.referer')
        ) : null;
    }

    protected function isSqlQueriesLoggableConnection($name) {
        return !in_array($name, $this->config->get('excludeConnection'));
    }

    private function getLogger() {
        return $this->logger;
    }
}
