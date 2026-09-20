<?php

class CEmail_Driver_SendGridDriver extends CEmail_DriverAbstract {
    public function send(array $to, $subject, $body, $options = []) {
        $apiKey = $this->config->getPassword();
        $from = carr::get($options, 'from', $this->config->getFrom());
        $fromName = carr::get($options, 'from_name', $this->config->getFromName());
        $attachments = carr::get($options, 'attachments', []);
        $replyTo = carr::get($options, 'replyTo', '');
        $cc = carr::get($options, 'cc', []);
        $bcc = carr::get($options, 'bcc', []);

        $mail = new CVendor_SendGrid_Mail_Mail();
        $mail->setFrom($from, $fromName);
        if (strlen($replyTo) > 0) {
            $mail->setReplyTo($replyTo);
        }

        $toSendGrid = [];
        if (!is_array($to)) {
            $to = [$to];
        }
        foreach ($to as $toItem) {
            $toName = '';
            $toEmail = $toItem;
            if (is_array($toItem)) {
                $toName = carr::get($toItem, 'toName');
                $toEmail = carr::get($toItem, 'toEmail');
            }
            $mail->addTo($toEmail, $toName);
        }
        $mail->setSubject($subject);
        $mail->addContent('text/html', $body);
        $cc = carr::wrap($cc);
        $bcc = carr::wrap($bcc);
        foreach ($cc as $email) {
            $mail->addCc($email);
        }
        foreach ($bcc as $email) {
            $mail->addBcc($email);
        }
        $subjectPreview = carr::get($options, 'subject_preview');
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
            $mail->addAttachment($attachment);
        }

        $sg = new CVendor_SendGrid($apiKey);

        $response = $sg->send($mail);
        if ($response->statusCode() >= 400) {
            throw new Exception('Fail to send mail, API Response:(' . $response->statusCode() . ')' . $response->body());
        }

        return $response;
    }
}
