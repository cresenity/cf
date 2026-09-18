<?php

defined('SYSPATH') or die('No direct access allowed.');

class CApp_ErrorHandler {
    /**
     * @var array
     */
    public static $errorLang = [
        E_RECOVERABLE_ERROR => [1, 'Recoverable Error', 'An error was detected which prevented the loading of this page. If this problem persists, please contact the website administrator.'],
        E_ERROR => [1, 'Fatal Error', ''],
        E_USER_ERROR => [1, 'Fatal Error', ''],
        E_PARSE => [1, 'Syntax Error', ''],
        E_WARNING => [1, 'Warning Message', ''],
        E_USER_WARNING => [1, 'Warning Message', ''],
        E_STRICT => [2, 'Strict Mode Error', ''],
        E_NOTICE => [2, 'Runtime Message', ''],
    ];

    /**
     * @param Exception   $exception
     * @param string|null $email
     * @param string|null $subject
     *
     * @return void|CVendor_SendGrid_Response
     */
    public static function sendExceptionEmail(Exception $exception, $email = null, $subject = null) {
        $html = static::getHtml($exception);
        $app = CApp::instance();
        $org = $app->org();
        $orgName = 'CAPP';
        $orgEmail = $orgName;
        if ($org != null) {
            $orgEmail = $org->name;
            $orgName = $org->name;
        }

        $ymd = date('Ymd');
        if ($subject == null) {
            $subject = 'Error Cresenity APP - ' . $orgName . ' on ' . CFRouter::getCompleteUri() . ' [' . $ymd . ']';
        }
        $headers = 'From: ' . strip_tags($orgEmail) . "\r\n";
        $headers .= 'Reply-To: ' . strip_tags($orgEmail) . "\r\n";
        //$headers .= "CC: susan@example.com\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";

        $message = $html;
        if ($email == null) {
            $email = ccfg::get('admin_email');
        }

        $smtpOptions = [];
        if (ccfg::get('mail_error_smtp')) {
            $smtpUsername = ccfg::get('smtp_username_error');
            $smtpPassword = ccfg::get('smtp_password_error');
            $smtpHost = ccfg::get('smtp_host_error');
            $smtpPort = ccfg::get('smtp_port_error');
            $secure = ccfg::get('smtp_secure_error');

            if (strlen($smtpUsername) > 0) {
                $smtpOptions['smtp_username'] = $smtpUsername;
            }
            if (strlen($smtpPassword) > 0) {
                $smtpOptions['smtp_password'] = $smtpPassword;
            }
            if (strlen($smtpHost) > 0) {
                $smtpOptions['smtp_host'] = $smtpHost;
            }
            if (strlen($smtpPort) > 0) {
                $smtpOptions['smtp_port'] = $smtpPort;
            }
            if (strlen($secure) > 0) {
                $smtpOptions['smtp_secure'] = $secure;
            }
        }

        return CEmail::sender($smtpOptions)->send($email, $subject . ' [FOR ADMINISTRATOR]', $message, $smtpOptions);
    }

    /**
     * @param Exception $exception
     *
     * @return string
     */
    public static function getHtml(Exception $exception) {
        $code = $exception->getCode();
        $type = get_class($exception);
        $message = $exception->getMessage();
        $file = $exception->getFile();
        $line = $exception->getLine();
        $app = CApp::instance();
        $org = $app->org();

        if (is_numeric($code)) {
            $codes = static::$errorLang;

            if (!empty($codes[$code])) {
                list($level, $error, $description) = $codes[$code];
            } else {
                $level = 1;
                $error = get_class($exception);
                $description = '';
            }
        } else {
            // Custom error message, this will never be logged
            $level = 5;
            $error = $code;
            $description = '';
        }
        // Remove the DOCROOT from the path, as a security precaution
        $file = str_replace('\\', '/', realpath($file));
        $file = preg_replace('|^' . preg_quote(DOCROOT) . '|', '', $file);

        // Test if display_errors is on
        $trace = false;
        $traceArray = false;
        if ($line != false) {
            // Remove the first entry of debug_backtrace(), it is the exception_handler call
            $trace = $exception->getTraceAsString();
        }

        $v = CView::factory('cresenity/mail/exception');
        $v->error = $error;
        $v->description = $description;
        $v->file = $file;
        $v->line = $line;
        $v->trace = $trace;
        $v->message = $message;
        $v->exception = $exception;
        $html = $v->render();

        return $html;
    }
}
