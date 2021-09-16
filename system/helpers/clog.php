<?php
//@codingStandardsIgnoreStart
class clog {
    /**
     * Log login
     *
     * @param int $user_id
     *
     * @return void
     */
    public static function login($user_id) {
        $app = CApp::instance();
        $app_id = $app->appId();
        $db = CDatabase::instance();
        $ip_address = CHTTP::request()->ip();
        $session_id = CSession::instance()->id();
        $browser = CHTTP::request()->browser()->getBrowser();
        $browser_version = CHTTP::request()->browser()->getVersion();
        $platform = CHTTP::request()->browser()->getPlatform();
        $platform_version = '';

        $user = cuser::get($user_id);
        $org_id = CF::orgId();
        $data = [
            'login_date' => date('Y-m-d H:i:s'),
            'org_id' => $org_id,
            'user_agent' => CF::userAgent(),
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
            'password' => $password,
            'error_message' => $errorMessage,
            'browser' => CHTTP::request()->browser()->getBrowser(),
            'browser_version' => CHTTP::request()->browser()->getVersion(),
            'platform' => CHTTP::request()->browser()->getPlatform(),
            'platform_version' => '',
            'remote_addr' => CHTTP::request()->ip(),
            'session_id' => CSession::instance()->id(),
            'app_id' => $app->appId(),
        ];
        return CDatabase::instance()->insert('log_login_fail', $data);
    }

    public static function log_print($user_id, $print_mode, $printer_type, $printer_name, $data_type, $print_ref_id, $print_ref_code) {
        $app = CApp::instance();
        $app_id = $app->app_id();
        $db = CDatabase::instance();
        $user = cuser::get($user_id);
        $user_id = $user->user_id;
        $org_id = $user->org_id;
        $ip_address = crequest::remote_address();
        $browser = crequest::browser();
        $browser_version = crequest::browser_version();
        $platform = crequest::platform();
        $platform_version = crequest::platform_version();

        $data = [
            'print_date' => date('Y-m-d H:i:s'),
            'org_id' => $org_id,
            'session_id' => CSession::instance()->id(),
            'user_agent' => CHTTP::request()->userAgent(),
            'browser' => $browser,
            'browser_version' => $browser_version,
            'platform' => $platform,
            'platform_version' => $platform_version,
            'remote_addr' => $ip_address,
            'user_id' => $user_id,
            'print_mode' => $print_mode,
            'printer_type' => $printer_type,
            'printer_name' => $printer_name,
            'data_type' => $data_type,
            'print_ref_id' => $print_ref_id,
            'print_ref_code' => $print_ref_code,
            'app_id' => $app_id,
        ];
        $db->insert('log_print', $data);
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

        $db = CDatabase::instance();
        $app = CApp::instance();
        $app_id = $app->app_id();
        $db = CDatabase::instance();
        $app = CApp::instance();
        $ip_address = crequest::remote_address();
        $session_id = CSession::instance()->id();
        $browser = crequest::browser();
        $browser_version = crequest::browser_version();
        $platform = crequest::platform();
        $platform_version = crequest::platform_version();
        $nav_name = '';
        $nav_label = '';
        $action_label = '';
        $action_name = '';
        $controller = crouter::controller();
        if ($controller == 'cresenity') {
            return false;
        }
        $method = crouter::method();
        $nav = cnav::nav();
        if ($nav != null) {
            $nav_name = $nav['name'];
            $nav_label = $nav['label'];
            if (isset($nav['action'])) {
                foreach ($nav['action'] as $act) {
                    if (isset($act['controller']) && isset($act['method']) && $act['controller'] == $controller && $act['method'] == $method) {
                        $action_name = $act['name'];
                        $action_label = $act['label'];
                    }
                }
            }
        }
        $org_id = CF::orgId();
        if ($org_id == null) {
            $user = cuser::get($user_id);
            if ($user != null) {
                $org_id = $user->org_id;
            }
        }
        $data = [
            'activity_date' => date('Y-m-d H:i:s'),
            'org_id' => $org_id,
            'session_id' => CSession::instance()->id(),
            'user_agent' => CF::userAgent(),
            'browser' => $browser,
            'browser_version' => $browser_version,
            'platform' => $platform,
            'platform_version' => $platform_version,
            'remote_addr' => $ip_address,
            'user_id' => $user_id,
            'uri' => crouter::complete_uri(),
            'routed_uri' => crouter::routed_uri(),
            'controller' => crouter::controller(),
            'method' => crouter::method(),
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
        $db = CDatabase::instance();
        $app = CApp::instance();
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
        $db = CDatabase::instance();
        $app = CApp::instance();
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
     * </pre>
     *
     * @param array/string $options
     *
     * @return bool
     */
    public static function write($options) {
        $clogger_instance = CLogger::instance();
        $level = CLogger::INFO;
        $message = $options;
        $param = [];
        if (is_array($options)) {
            $message = carr::get($options, 'message');
            $filename = carr::get($options, 'filename');
            $level = carr::get($options, 'level');
            $path = carr::get($options, 'path');

            $param['path'] = $path;
        }
        return $clogger_instance->add($level, $message);
    }

    const EMERGENCY = LOG_EMERG;    // 0

    const ALERT = LOG_ALERT;    // 1

    const CRITICAL = LOG_CRIT;     // 2

    const ERROR = LOG_ERR;      // 3

    const WARNING = LOG_WARNING;  // 4

    const NOTICE = LOG_NOTICE;   // 5

    const INFO = LOG_INFO;     // 6

    const DEBUG = LOG_DEBUG;    // 7

    public static function emergency($message) {
        return CLogger::instance()->add(CLogger::EMERGENCY, $message);
    }

    public static function alert($message) {
        return CLogger::instance()->add(CLogger::ALERT, $message);
    }

    public static function critical($message) {
        return CLogger::instance()->add(CLogger::CRITICAL, $message);
    }

    public static function error($message) {
        return CLogger::instance()->add(CLogger::ERROR, $message);
    }

    public static function warning($message) {
        return CLogger::instance()->add(CLogger::WARNING, $message);
    }

    public static function notice($message) {
        return CLogger::instance()->add(CLogger::NOTICE, $message);
    }

    public static function info($message) {
        return CLogger::instance()->add(CLogger::INFO, $message);
    }

    public static function debug($message) {
        return CLogger::instance()->add(CLogger::DEBUG, $message);
    }

    public static function login_fail($username, $password, $error_message) {
        return static::loginFail($username, $password, $error_message);
    }
}
