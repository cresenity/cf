<?php

//@codingStandardsIgnoreStart
/**
 * @deprecated 1.6, use CEmail
 */
class cmailapi {
    /**
     * @deprecated since 1.2 use CEmail
     *
     * @param mixed $to
     * @param mixed $subject
     * @param mixed $message
     * @param mixed $attachments
     * @param mixed $cc
     * @param mixed $bcc
     * @param mixed $options
     */
    public static function sendgridv3($to, $subject, $message, $attachments = [], $cc = [], $bcc = [], $options = []) {
        CF::deprecated(__METHOD__, 'CEmail', '1.2', null, true);
        $options['driver'] = 'sendgrid';
        $options['cc'] = $cc;
        $options['bcc'] = $bcc;
        $options['attachments'] = $attachments;
        CEmail::sender($options)->send($to, $subject, $message, $options);

//         $smtp_password = carr::get($options, 'smtp_password');
//         $smtp_host = carr::get($options, 'smtp_host');
//         if (!$smtp_password) {
//             $smtp_password = ccfg::get('smtp_password');
//         }
//         if (!$smtp_host) {
//             $smtp_host = ccfg::get('smtp_host');
//         }
//         if ($smtp_host != 'smtp.sendgrid.net') {
//             throw new Exception('Fail to send mail API, SMTP Host is not valid');
//         }
//         $sendgrid_apikey = $smtp_password;

//         $smtp_from = carr::get($options, 'smtp_from');
//         if ($smtp_from == null) {
//             $smtp_from = ccfg::get('smtp_from');
//         }
//         $smtp_from_name = carr::get($options, 'smtp_from_name');
//         if ($smtp_from_name == null) {
//             $smtp_from_name = ccfg::get('smtp_from_name');
//         }

// //        if (is_array($to)) {
// //            $to = carr::get($to, 0);
// //        }
//         $mail = new CVendor_SendGrid_Mail_Mail();
//         $mail->setFrom($smtp_from, $smtp_from_name);

//         $toSendGrid = [];
//         if (!is_array($to)) {
//             $to = [$to];
//         }
//         foreach ($to as $toItem) {
//             $toName = '';
//             $toEmail = $toItem;
//             if (is_array($toItem)) {
//                 $toName = carr::get($toItem, 'toName');
//                 $toEmail = carr::get($toItem, 'toEmail');
//             }
//             $mail->addTo($toEmail, $toName);
//         }
//         $mail->setSubject($subject);
//         $mail->addContent('text/html', $message);

//         if (!is_array($attachments)) {
//             $attachments = [];
//         }

//         $subjectPreview = carr::get($options, 'subject_preview');
//         foreach ($attachments as $att) {
//             $disk = '';
//             if (is_array($att)) {
//                 $path = carr::get($att, 'path');
//                 $filename = basename($path);
//                 $attachmentFilename = carr::get($att, 'filename');
//                 $type = carr::get($att, 'type');
//                 $disk = carr::get($att, 'disk');
//             } else {
//                 $path = $att;
//                 $filename = basename($att);
//                 $attachmentFilename = $filename;
//                 $type = '';
//             }

//             if (strlen($type) == 0) {
//                 $ext = pathinfo($filename, PATHINFO_EXTENSION);

//                 $type = 'application/text';
//                 if ($ext == 'pdf') {
//                     $type = 'application/pdf';
//                 }
//                 if ($ext == 'jpg' || $ext == 'jpeg') {
//                     $type = 'image/jpeg';
//                 }
//                 if ($ext == 'png') {
//                     $type = 'image/png';
//                 }
//             }
//             $content = '';
//             if (strlen($disk) > 0) {
//                 $diskObject = CStorage::instance()->disk($disk);
//                 $content = $diskObject->get($path);
//             } else {
//                 $content = file_get_contents($path);
//             }
//             $attachment = new CVendor_SendGrid_Mail_Attachment();
//             $attachment->setContent(base64_encode($content));
//             $attachment->setType($type);
//             $attachment->setDisposition('attachment');
//             $attachment->setFilename($attachmentFilename);
//             $mail->addAttachment($attachment);
//         }

//         $sg = new CVendor_SendGrid($sendgrid_apikey);

//         //cdbg::var_dump(json_encode($mail, JSON_PRETTY_PRINT));

//         $response = $sg->send($mail);
//         if ($response->statusCode() > 400) {
//             throw new Exception('Fail to send mail, API Response:(' . $response->statusCode() . ')' . $response->body());
//         }
//         return $response;
    }

    /**
     * @deprecated since 1.2 use CEmail
     *
     * @param mixed $to
     * @param mixed $subject
     * @param mixed $message
     * @param mixed $attachments
     * @param mixed $cc
     * @param mixed $bcc
     * @param mixed $options
     */
    public static function sendgrid($to, $subject, $message, $attachments = [], $cc = [], $bcc = [], $options = []) {
        CF::deprecated(__METHOD__, 'CEmail', '1.2', null, true);
        $options['driver'] = 'sendgrid';
        $options['cc'] = $cc;
        $options['bcc'] = $bcc;
        $options['attachments'] = $attachments;
        CEmail::sender($options)->send($to, $subject, $message, $options);
        // //$sendgrid_apikey = "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";

        // $smtp_password = carr::get($options, 'smtp_password');
        // $smtp_host = carr::get($options, 'smtp_host');
        // if (!$smtp_password) {
        //     $smtp_password = ccfg::get('smtp_password');
        // }
        // if (!$smtp_host) {
        //     $smtp_host = ccfg::get('smtp_host');
        // }
        // if ($smtp_host != 'smtp.sendgrid.net') {
        //     throw new Exception('Fail to send mail API, SMTP Host is not valid');
        // }
        // $sendgrid_apikey = $smtp_password;
        // $smtp_from = carr::get($options, 'smtp_from');
        // if ($smtp_from == null) {
        //     $smtp_from = ccfg::get('smtp_from');
        // }
        // $smtp_from_name = carr::get($options, 'smtp_from_name');
        // if ($smtp_from_name == null) {
        //     $smtp_from_name = ccfg::get('smtp_from_name');
        // }

        // $url = 'https://api.sendgrid.com/';
        // $pass = $sendgrid_apikey;
        // /*
        //   $template_id = '<your_template_id>';
        //   $js = array(
        //   'sub' => array(':name' => array('Elmer')),
        //   'filters' => array('templates' => array('settings' => array('enable' => 1, 'template_id' => $template_id)))
        //   );
        //  */

        // $files = [];

        // $params = [
        //     'to' => $to,
        //     'cc' => $cc,
        //     'bcc' => $bcc,
        //     'from' => $smtp_from,
        //     'fromname' => $smtp_from_name,
        //     'subject' => $subject . '',
        //     'html' => $message,
        // ];

        // $request = $url . 'api/mail.send.json';

        // // Generate curl request
        // $session = curl_init($request);
        // // Tell PHP not to use SSLv3 (instead opting for TLS)
        // curl_setopt($session, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        // curl_setopt($session, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $sendgrid_apikey]);
        // // Tell curl to use HTTP POST
        // curl_setopt($session, CURLOPT_POST, true);
        // // Tell curl that this is the body of the POST
        // curl_setopt($session, CURLOPT_POSTFIELDS, curl::asPostString($params));
        // // Tell curl not to return headers, but do return the response
        // curl_setopt($session, CURLOPT_HEADER, false);
        // curl_setopt($session, CURLOPT_RETURNTRANSFER, true);

        // if (count($files) > 0) {
        //     //curl_setopt($session, CURLOPT_SAFE_UPLOAD, false);
        // }
        // // obtain response
        // $response = curl_exec($session);
        // curl_close($session);

        // $response_array = json_decode($response, true);
        // if (carr::get($response_array, 'message') != 'success') {
        //     throw new Exception('Fail to send mail, API Response:' . $response);
        // }
        // return $response_array;
    }

    public static function mailgun($to, $subject, $message, $attachments = [], $cc = [], $bcc = [], $options = []) {
        CF::deprecated(__METHOD__, 'CEmail', '1.6', null, true);
        //$sendgrid_apikey = "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";
        //public key: pubkey-c338bfc1568e4d6e79331119e6c56645
        //private key: key-5f194bedfdade1fa513910895857d447
        $smtp_password = carr::get($options, 'smtp_password');
        $smtp_host = carr::get($options, 'smtp_host');
        if (!$smtp_password) {
            $smtp_password = ccfg::get('smtp_password');
        }
        if (!$smtp_host) {
            $smtp_host = ccfg::get('smtp_host');
        }
        if ($smtp_host != 'smtp.mailgun.org') {
            throw new Exception('Fail to send mail API, SMTP Host is not valid Mailgun SMTP');
        }
        $mailgun_apikey = $smtp_password;
        $smtp_from = carr::get($options, 'smtp_from');
        if ($smtp_from == null) {
            $smtp_from = ccfg::get('smtp_from');
        }
        $smtp_from_name = carr::get($options, 'smtp_from_name');
        if ($smtp_from_name == null) {
            $smtp_from_name = ccfg::get('smtp_from_name');
        }

        $smtp_domain = carr::get($options, 'smtp_domain');
        if ($smtp_domain == null) {
            $smtp_domain = ccfg::get('smtp_domain');
        }
        if ($smtp_domain == null) {
            $smtp_domain = 'mg.compro.id';
        }
        $url = 'https://api.mailgun.net/v3/' . $smtp_domain . '/messages';
        $pass = $mailgun_apikey;
        /*
          $template_id = '<your_template_id>';
          $js = array(
          'sub' => array(':name' => array('Elmer')),
          'filters' => array('templates' => array('settings' => array('enable' => 1, 'template_id' => $template_id)))
          );
         */

        $files = [];

        $params = [
            'to' => $to,
            'from' => $smtp_from,
            'subject' => $subject . '',
            'html' => $message,
        ];

        if (count($cc) > 0) {
            $params['cc'] = $cc;
        }
        if (count($bcc) > 0) {
            $params['bcc'] = $bcc;
        }

        // Generate curl request
        $session = curl_init($url);
        curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($session, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($session, CURLOPT_USERPWD, 'api:' . $mailgun_apikey);
        curl_setopt($session, CURLOPT_ENCODING, 'UTF-8');
        // Tell curl to use HTTP POST
        curl_setopt($session, CURLOPT_POST, true);
        // Tell curl that this is the body of the POST
        curl_setopt($session, CURLOPT_POSTFIELDS, curl::asPostString($params));
        // Tell curl not to return headers, but do return the response
        curl_setopt($session, CURLOPT_HEADER, false);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);

        if (count($files) > 0) {
            //curl_setopt($session, CURLOPT_SAFE_UPLOAD, false);
        }
        // obtain response
        $response = curl_exec($session);
        curl_close($session);

        $response_array = json_decode($response, true);
        if (strlen(carr::get($response_array, 'id')) == 0) {
            throw new Exception('Fail to send mail, API Response:' . $response);
        }

        return $response_array;
    }

    public static function elasticemail($to, $subject, $message, $attachments = [], $cc = [], $bcc = [], $options = []) {
        CF::deprecated(__METHOD__, 'CEmail', '1.6', null, true);
        $smtp_password = carr::get($options, 'smtp_password');
        $smtp_host = carr::get($options, 'smtp_host');
        if (!$smtp_password) {
            $smtp_password = ccfg::get('smtp_password');
        }
        if (!$smtp_host) {
            $smtp_host = ccfg::get('smtp_host');
        }
        if ($smtp_host != 'smtp.elasticemail.com' && $smtp_host != 'smtp25.elasticemail.com') {
            throw new Exception('Fail to send mail API, SMTP Host is not valid');
        }

        $smtp_from = carr::get($options, 'smtp_from');
        if ($smtp_from == null) {
            $smtp_from = ccfg::get('smtp_from');
        }
        $smtp_from_name = carr::get($options, 'smtp_from_name');
        if ($smtp_from_name == null) {
            $smtp_from_name = ccfg::get('smtp_from_name');
        }

        $url = 'https://api.elasticemail.com/v2/email/send';

        try {
            if (!is_array($to)) {
                $to = [$to];
            }
            if (!is_array($cc)) {
                $cc = [$cc];
            }
            if (!is_array($bcc)) {
                $bcc = [$bcc];
            }

            $to_implode = implode(';', $to);
            $cc_implode = implode(';', $cc);
            $bcc_implode = implode(';', $bcc);
            $post = ['from' => $smtp_from,
                'fromName' => $smtp_from_name,
                'apikey' => $smtp_password,
                'subject' => $subject . '[API]',
                'msgTo' => $to_implode,
                'msgCC' => $cc_implode,
                'msgBcc' => $bcc_implode,
                'bodyHtml' => $message,
                'isTransactional' => true
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            $response_array = json_decode($response, true);
            if (!carr::get($response_array, 'success')) {
                throw new Exception('Fail to send mail, API Response:' . $response);
            }
        } catch (Exception $ex) {
            return $ex;
        }

        return true;
    }

    public static function postmark($to, $subject, $message, $attachments = [], $cc = [], $bcc = [], $options = []) {
        CF::deprecated(__METHOD__, 'CEmail', '1.6', null, true);
        $smtp_password = carr::get($options, 'smtp_password');
        $smtp_host = carr::get($options, 'smtp_host');
        if (!$smtp_password) {
            $smtp_password = ccfg::get('smtp_password');
        }
        if (!$smtp_host) {
            $smtp_host = ccfg::get('smtp_host');
        }
        if ($smtp_host != 'smtp.postmarkapp.com') {
            throw new Exception('Fail to send mail API, SMTP Host is not valid');
        }

        $smtp_from = carr::get($options, 'smtp_from');
        if ($smtp_from == null) {
            $smtp_from = ccfg::get('smtp_from');
        }
        $smtp_from_name = carr::get($options, 'smtp_from_name');
        if ($smtp_from_name == null) {
            $smtp_from_name = ccfg::get('smtp_from_name');
        }

        $url = 'https://api.postmarkapp.com/email';

        try {
            if (!is_array($to)) {
                $to = [$to];
            }
            if (!is_array($cc)) {
                $cc = [$cc];
            }
            if (!is_array($bcc)) {
                $bcc = [$bcc];
            }

            $to_implode = implode(',', $to);
            $cc_implode = implode(',', $cc);
            $bcc_implode = implode(',', $bcc);
            $post = [];
            $post['From'] = $smtp_from;
            $post['To'] = $to_implode;
            $post['Cc'] = $cc_implode;
            $post['Bcc'] = $bcc_implode;
            $post['Subject'] = $subject;
            $post['HtmlBody'] = $message;

            $version = phpversion();
            $os = PHP_OS;

            $json = json_encode($post);
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => [
                    'User-Agent: Postmark-PHP (PHP Version:' . $version . ', OS:' . $os . ')',
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'X-Postmark-Server-Token:' . $smtp_password
                ],
            ]);

            $response = curl_exec($ch);
            curl_close($ch);
            $response_array = json_decode($response, true);
            if (!carr::get($response_array, 'success')) {
                throw new Exception('Fail to send mail, API Response:' . $response);
            }
        } catch (Exception $ex) {
            throw $ex;
        }

        return true;
    }
}
