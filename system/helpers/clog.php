<?php
//@codingStandardsIgnoreStart
/**
 * @deprecated since 1.6, use c::log()
 */
class clog {
    const EMERGENCY = LOG_EMERG;    // 0

    const ALERT = LOG_ALERT;    // 1

    const CRITICAL = LOG_CRIT;     // 2

    const ERROR = LOG_ERR;      // 3

    const WARNING = LOG_WARNING;  // 4

    const NOTICE = LOG_NOTICE;   // 5

    const INFO = LOG_INFO;     // 6

    const DEBUG = LOG_DEBUG;    // 7

    /**
     * Log login.
     *
     * @param int $user_id
     *
     * @return void
     */
    public static function login($user_id) {
        $app = CApp::instance();
        $app_id = $app->appId();
        $db = c::db();
        $ip_address = CHTTP::request()->ip();
        $session_id = CSession::instance()->id();
        $browser = CHTTP::request()->browser()->getBrowser();
        $browser_version = CHTTP::request()->browser()->getVersion();
        $platform = CHTTP::request()->browser()->getPlatform();
        $platform_version = '';

        $org_id = CF::orgId();
        $data = [
            'login_date' => date('Y-m-d H:i:s'),
            'org_id' => $org_id,
            'user_agent' => CHTTP::request()->userAgent(),
            'browser' => $browser,
            'browser_version' => $browser_version,
            'platform' => $platform,
            'platform_version' => $platform_version,
            'remote_addr' => $ip_address,
            'user_id' => $user_id,
            'session_id' => $session_id,
            'app_id' => $app_id,
        ];
        $db->insert('log_login', $data);
    }

    public static function loginFail($username, $password, $errorMessage) {
        $app = CApp::instance();

        $data = [
            'login_fail_date' => date('Y-m-d H:i:s'),
            'org_id' => null,
            'user_agent' => CHTTP::request()->userAgent(),
            'username' => $username,
            'password' => $password === null ? null : (string) strlen($password), // panjang saja, bukan sandi mentah
            'error_message' => $errorMessage,
            'browser' => CHTTP::request()->browser()->getBrowser(),
            'browser_version' => CHTTP::request()->browser()->getVersion(),
            'platform' => CHTTP::request()->browser()->getPlatform(),
            'platform_version' => '',
            'remote_addr' => CHTTP::request()->ip(),
            'session_id' => CSession::instance()->id(),
            'app_id' => $app->appId(),
        ];

        return c::db()->insert('log_login_fail', $data);
    }

    public static function request($user_id = null) {
        CApp_Log_Request::populate();
    }

    public static function activity($param, $activity_type = '', $description = '') {
        $data_before = [];
        $data_after = [];
        if (!is_array($param)) {
            $user_id = $param;
        } else {
            $user_id = carr::get($param, 'user_id');
            $data_before = carr::get($param, 'before', []);
            $data_after = carr::get($param, 'after', []);
        }

        $data_before = json_encode($data_before);
        $data_after = json_encode($data_after);

        $app = c::app();
        $app_id = $app->appId();
        $db = c::db();
        $app = CApp::instance();
        $ip_address = CHTTP::request()->ip();
        $session_id = CSession::instance()->id();
        $browser = crequest::browser();
        $browser_version = CHTTP::request()->browser()->getVersion();
        $platform = crequest::platform();
        $platform_version = '';
        $nav_name = '';
        $nav_label = '';
        $action_label = '';
        $action_name = '';
        $controller = CFRouter::getController();
        if ($controller == 'cresenity') {
            return false;
        }
        $method = CFRouter::getControllerMethod();
        $nav = cnav::nav();
        if ($nav != null) {
            $nav_name = $nav['name'];
            $nav_label = $nav['label'];
            if (isset($nav['action'])) {
                foreach ($nav['action'] as $act) {
                    if (isset($act['controller'], $act['method']) && $act['controller'] == $controller && $act['method'] == $method) {
                        $action_name = $act['name'];
                        $action_label = $act['label'];
                    }
                }
            }
        }
        $org_id = CF::orgId();

        $data = [
            'activity_date' => date('Y-m-d H:i:s'),
            'org_id' => $org_id,
            'session_id' => CSession::instance()->id(),
            'user_agent' => CHTTP::request()->userAgent(),
            'browser' => $browser,
            'browser_version' => $browser_version,
            'platform' => $platform,
            'platform_version' => $platform_version,
            'remote_addr' => $ip_address,
            'user_id' => $user_id,
            'uri' => crouter::complete_uri(),
            'routed_uri' => crouter::routed_uri(),
            'controller' => CFRouter::getController(),
            'method' => CFRouter::getControllerMethod(),
            'query_string' => crouter::query_string(),
            'nav' => $nav_name,
            'nav_label' => $nav_label,
            'action' => $action_name,
            'action_label' => $action_label,
            'activity_type' => $activity_type,
            'description' => $description,
            'app_id' => $app_id,
            'data_before' => $data_before,
            'data_after' => $data_after,
        ];
        $db->insert('log_activity', $data);
    }

    public static function backup($user_id, $filename, $directory = '') {
        $db = c::db();
        $app = c::app();
        $org = $app->org();
        $org_id = null;
        if ($org != null) {
            $org_id = $org->org_id;
        }
        $data = [
            'backup_date' => date('Y-m-d H:i:s'),
            'org_id' => $org_id,
            'user_id' => $user_id,
            'dir' => $directory,
            'filename' => $filename,
            'app_id' => CF::config('cresenity.app_id'),
        ];
        $db->insert('log_backup', $data);
    }

    public static function cleanup($user_id) {
        $db = c::db();
        $app = c::app();
        $org = $app->org();
        $org_id = null;
        $data = [
            'cleanup_date' => date('Y-m-d H:i:s'),
            'org_id' => $org_id,
            'user_id' => $user_id,
            'app_id' => CF::config('cresenity.app_id'),
        ];
        $db->insert('log_cleanup', $data);
    }

    public static function log($filename, $type, $message) {
        $date = date('Y-m-d H:i:s');
        $str = $date . ' ' . $type . ' ' . $message . "\r\n";
        $dir = DOCROOT . 'logs/';
        if (!is_dir($dir)) {
            @mkdir($dir);
        }
        $filename = $dir . date('Ymd') . '_' . $filename;
        $fh = @fopen($filename, 'a+');
        fwrite($fh, $str);
        @fclose($fh);
    }

    /**
     * This function is used for log for any statement. <br/>
     * Here is inline an example:
     * <pre>
     *  <code>
     *      <?php clog::write('Test');?>
     *  </code>
     * </pre>.
     *
     * @param array/string $options
     * @param mixed $message
     *
     * @return bool
     */
    public static function write($message) {
        $level = CLogger::INFO;

        return CLogger::logger()->log($level, $message);
    }

    public static function emergency($message) {
        return CLogger::logger()->emergency($message);
    }

    public static function alert($message) {
        return CLogger::logger()->alert($message);
    }

    public static function critical($message) {
        return CLogger::logger()->critical($message);
    }

    public static function error($message) {
        return CLogger::logger()->error($message);
    }

    public static function warning($message) {
        return CLogger::logger()->warning($message);
    }

    public static function notice($message) {
        return CLogger::logger()->notice($message);
    }

    public static function info($message) {
        return CLogger::logger()->info($message);
    }

    public static function debug($message) {
        return CLogger::logger()->debug($message);
    }

    public static function login_fail($username, $password, $error_message) {
        return self::loginFail($username, $password, $error_message);
    }
}
