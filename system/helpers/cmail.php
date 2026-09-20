<?php

defined('SYSPATH') or die('No direct access allowed.');

//@codingStandardsIgnoreStart
/**
 * @deprecated 1.6, use CEmail
 */
class cmail {
    /**
     * @param string|array $to          to email
     * @param string       $subject     subject of email
     * @param string       $message     body message of email
     * @param array        $attachments
     * @param array        $cc
     * @param array        $bcc
     * @param array        $options     Options available smtp_username,smtp_password,dll
     *
     * @throws Exception
     *
     * @return void|CVendor_SendGrid_Response
     *
     * @deprecated since 1.2 use CEmail
     */
    //@codingStandardsIgnoreStart
    public static function send_smtp($to, $subject, $message, $attachments = [], $cc = [], $bcc = [], $options = []) {
        CF::deprecated(__METHOD__, 'CEmail', '1.2', null, true);
        //@codingStandardsIgnoreEnd
        $options['cc'] = $cc;
        $options['bcc'] = $bcc;
        $options['attachments'] = $attachments;

        return CEmail::sender($options)->send($to, $subject, $message, $options);

        // $smtp_username = carr::get($options, 'smtp_username');
        // $smtp_password = carr::get($options, 'smtp_password');
        // $smtp_host = carr::get($options, 'smtp_host');
        // $smtp_port = carr::get($options, 'smtp_port');
        // $secure = carr::get($options, 'smtp_secure');

        // if (!$smtp_username) {
        //     $smtp_username = ccfg::get('smtp_username');
        // }
        // if (!$smtp_password) {
        //     $smtp_password = ccfg::get('smtp_password');
        // }
        // if (!$smtp_host) {
        //     $smtp_host = ccfg::get('smtp_host');
        // }
        // if (!$smtp_port) {
        //     $smtp_port = ccfg::get('smtp_port');
        // }
        // if (!$secure) {
        //     $secure = ccfg::get('smtp_secure');
        // }

        // switch ($smtp_host) {
        //     case 'smtp.sendgrid.net':
        //         return cmailapi::sendgridv3($to, $subject, $message, $attachments, $cc, $bcc, $options);
        //         break;
        //     case 'smtp.mailgun.org':
        //         return cmailapi::mailgun($to, $subject, $message, $attachments, $cc, $bcc, $options);
        //         break;
        //     case 'smtp.elasticemail.com':
        //     case 'smtp25.elasticemail.com':
        //         if (count($attachments) == 0) {
        //             return cmailapi::elasticemail($to, $subject, $message, $attachments, $cc, $bcc, $options);
        //         }
        //         break;
        //     case 'smtp.postmarkapp.com':
        //         if (count($attachments) == 0) {
        //             return cmailapi::postmark($to, $subject, $message, $attachments, $cc, $bcc, $options);
        //         }
        //         break;
        // }
        // $mail = CSMTP::factory();
        // $mail->set_username($smtp_username);
        // $mail->set_password($smtp_password);
        // $mail->set_host($smtp_host);
        // $mail->set_port($smtp_port);

        // if ($secure == 'ssl') {
        //     $mail->set_ssl();
        // }
        // if ($secure == 'tls') {
        //     $mail->set_tls();
        // }

        // $smtp_from = carr::get($options, 'smtp_from');
        // if ($smtp_from == null) {
        //     $smtp_from = ccfg::get('smtp_from');
        // }
        // $smtp_from_name = carr::get($options, 'smtp_from_name');
        // if ($smtp_from_name == null) {
        //     $smtp_from_name = ccfg::get('smtp_from_name');
        // }
        // $mail->set_from($smtp_from, $smtp_from_name);

        // $mail->set_message_html($message);
        // $mail->set_subject($subject);
        // if (!is_array($to)) {
        //     $to = [$to];
        // }
        // foreach ($to as $em) {
        //     $mail->add_to($em);
        // }

        // if (!is_array($cc)) {
        //     $cc = [$cc];
        // }
        // foreach ($cc as $cc_k => $cc_v) {
        //     $mail->add_cc($cc_v);
        // }
        // if (!is_array($bcc)) {
        //     $bcc = [$bcc];
        // }
        // foreach ($bcc as $bcc_k => $bcc_v) {
        //     $mail->add_bcc($bcc_v);
        // }

        // foreach ($attachments as $attachment) {
        //     $data = carr::get($attachment, 'data');
        //     $name = carr::get($attachment, 'name');
        //     $encoding = carr::get($attachment, 'encoding', 'base64');
        //     $type = carr::get($attachment, 'type', 'application/octet-stream');
        //     $mail->add_attachment_string($data, $name, $encoding, $type);
        // }
        // try {
        //     $mail->send();
        // } catch (Exception $ex) {
        //     // die($ex->getMessage());
        //     throw $ex;
        // }
    }

    public static function send($to, $subject, $message, $headers) {
        CF::deprecated(__METHOD__, 'CEmail', '1.6', null, true);
        return @mail($to, $subject, $message, $headers);
    }
}
