<?php

/**
 * @deprecated 1.9 jalur pengirim lama; pakai CEmail::mailer() dengan transport `kirimemail` (atau nyalakan `email.legacy_sender_via_mailer`)
 */
class CEmail_Driver_KirimEmailDriver extends CEmail_DriverAbstract {
    public function send(array $to, $subject, $body, $options = []) {
        $apiKey = $this->config->getPassword();
        $username = $this->config->getUsername();
        $time = time();
        $generatedToken = hash_hmac('sha256', $username . '::' . $apiKey . '::' . $time, $apiKey);

        $domain = carr::get($options, 'domain', carr::get($options, 'smtp_domain'));

        $from = carr::get($options, 'from', $this->config->getFrom());
        $fromName = carr::get($options, 'from_name', $this->config->getFromName());
        $attachments = carr::get($options, 'attachments', []);
        $replyTo = carr::get($options, 'replyTo', '');
        $cc = carr::get($options, 'cc', []);
        $bcc = carr::get($options, 'bcc', []);

        $toEmailConcat = '';
        if (!is_array($to)) {
            $to = [$to];
        }

        foreach ($to as $toItem) {
            $toName = '';
            $toEmail = $toItem;
            $email = $toEmail;
            if (is_array($toItem)) {
                $toName = carr::get($toItem, 'toName');
                $toEmail = carr::get($toItem, 'toEmail');
                $email = $toName . '" <' . $toEmail . '>';
            }
            if (strlen($toEmailConcat) > 0) {
                $toEmailConcat .= ';';
            }
            $toEmailConcat .= $email;
        }
        $cc = carr::wrap($cc);
        $bcc = carr::wrap($bcc);

        foreach ($attachments as $att) {
            $disk = '';
            if (is_array($att)) {
                $path = carr::get($att, 'path');
                $filename = basename($path);
                $attachmentFilename = carr::get($att, 'filename');
                $type = carr::get($att, 'type');
                $disk = carr::get($att, 'disk');
            } else {
                $path = $att;
                $filename = basename($att);
                $attachmentFilename = $filename;
                $type = '';
            }

            if (strlen($type) == 0) {
                $ext = pathinfo($filename, PATHINFO_EXTENSION);

                $type = 'application/text';
                if ($ext == 'pdf') {
                    $type = 'application/pdf';
                }
                if ($ext == 'jpg' || $ext == 'jpeg') {
                    $type = 'image/jpeg';
                }
                if ($ext == 'png') {
                    $type = 'image/png';
                }
            }
            $content = '';
            if (strlen($disk) > 0) {
                $diskObject = CStorage::instance()->disk($disk);
                $content = $diskObject->get($path);
            } else {
                $content = file_get_contents($path);
            }
            $attachment = new CVendor_SendGrid_Mail_Attachment();
            $attachment->setContent(base64_encode($content));
            $attachment->setType($type);
            $attachment->setDisposition('attachment');
            $attachment->setFilename($attachmentFilename);
        }

        //build params
        $auth = base64_encode('api:' . $apiKey);
        $apiEndPoint = 'https://aplikasi.kirim.email/api/v3/transactional/messages';
        $headers = [
            'Domain' => $domain,
            'Authorization' => 'Basic ' . $auth,
        ];

        $post = [
            'from' => $from,
            'to' => $toEmailConcat,
            'subject' => $subject,
            'html' => $body,

        ];
        if (strlen($fromName) > 0) {
            $post['from_name'] = $fromName;
        }
        if (strlen($replyTo) > 0) {
            $post['headers']['Reply-To'] = $replyTo;
        }

        $httpHeaders = array_values(c::collect($headers)->map(function ($value, $key) {
            return $key . ':' . $value;
        })->toArray());

        $curl = CCurl::factory($apiEndPoint);

        $curl->setHttpHeader($httpHeaders);
        $curl->setPost($post);

        $response = $curl->exec()->response();


        return $response;
    }
}
