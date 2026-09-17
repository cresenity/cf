<?php

defined('SYSPATH') or die('No direct access allowed.');
/**
 * CSMTP. Helper class to make smtp mail creation easier.
 *
 * @deprecated 1.8
 */
require_once dirname(__FILE__) . '/Lib/phpmailer/class.phpmailer.php';
require_once dirname(__FILE__) . '/Lib/phpmailer/class.smtp.php';
require_once dirname(__FILE__) . '/Lib/phpmailer/class.pop3.php';

//@codingStandardsIgnoreStart
class CSMTP {
    private $smtp;

    public function __construct() {
        $this->smtp = new PHPMailer();
        $this->smtp->IsSMTP();
        $this->smtp->CharSet = 'UTF-8';
        $this->smtp->SMTPDebug = 0;
        if (isset($_GET['debug_email'])) {
            $this->smtp->SMTPDebug = 9;
        }
        $this->smtp->IsHTML(false);
    }

    public static function factory($headers = []) {
        $s = new CSMTP();

        return $s;
    }

    public function set_username($username) {
        $this->smtp->SMTPAuth = true;
        $this->smtp->Username = $username;

        return $this;
    }

    public function set_host($host) {
        $this->smtp->Host = $host;

        return $this;
    }

    public function set_port($port) {
        $this->smtp->Port = $port;

        return $this;
    }

    public function set_password($password) {
        $this->smtp->Password = $password;

        return $this;
    }

    public function set_subject($subject) {
        $this->smtp->Subject = $subject;

        return $this;
    }

    public function set_body($body) {
        $this->smtp->Body = $body;
    }

    public function set_message_html($html) {
        $this->set_body_html($html);
    }

    public function set_body_html($body) {
        $this->set_html(true);
        $this->smtp->Body = $body;
    }

    public function set_html($bool) {
        $this->smtp->IsHTML($bool);
        if ($bool) {
            $this->smtp->AltBody = 'To view the message, please use an HTML compatible email viewer!';
        }
    }

    public function set_charset($charset) {
        $this->smtp->CharSet = $charset;

        return $this;
    }

    public function set_ssl() {
        $this->smtp->SMTPSecure = 'ssl';

        return $this;
    }

    public function set_tls() {
        $this->smtp->SMTPSecure = 'tls';

        return $this;
    }

    public function set_secure($secure_type) {
        $this->smtp->SMTPSecure = $secure_type;

        return $this;
    }

    public function set_from($from_email, $from_name = '') {
        if (strlen($from_name) == 0) {
            $from_name = $from_email;
        }
        $this->smtp->setFrom($from_email, $from_name);

        return $this;
    }

    public function add_reply_to($email, $name = '') {
        if (strlen($name) == 0) {
            $name = $email;
        }
        $this->smtp->AddReplyTo($email, $name);

        return $this;
    }

    public function add_attachment($path, $name = '') {
        $this->smtp->AddAttachment($path, $name);

        return $this;
    }

    public function add_attachment_string($str, $filename = '', $encoding = 'base64', $type = 'application/octet-stream') {
        $this->smtp->AddStringAttachment($str, $filename, $encoding, $type);

        return $this;
    }

    public function add_to($email, $name = '') {
        if (strlen($name) == 0) {
            $name = $email;
        }
        $this->smtp->AddAddress($email, $name);

        return $this;
    }

    public function add_cc($email, $name = '') {
        if (strlen($name) == 0) {
            $name = $email;
        }
        $this->smtp->AddCC($email, $name);

        return $this;
    }

    public function add_bcc($email, $name = '') {
        if (strlen($name) == 0) {
            $name = $email;
        }
        $this->smtp->AddBCC($email, $name);

        return $this;
    }

    public function send() {
        if (!$this->smtp->Send()) {
            throw new Exception($this->smtp->ErrorInfo);
        }
    }
}
