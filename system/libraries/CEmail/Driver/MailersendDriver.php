<?php

class CEmail_Driver_MailersendDriver extends CEmail_DriverAbstract {
    public function send(array $to, $subject, $body, $options = []) {
        $apiKey = $this->config->getPassword();
        $errCode = 0;
        $errMessage = '';
        $from = carr::get($options, 'from', $this->config->getFrom());
        $fromName = carr::get($options, 'from_name', $this->config->getFromName());
        $attachments = carr::get($options, 'attachments', []);
        $mailersend = new CVendor_MailerSend(['api_key' => $apiKey]);
        $bulkEmailParams = [];

        if (!$from) {
            $errCode++;
            $errMessage = 'From Empty';
        }
        if (!$fromName) {
            $errCode++;
            $errMessage = 'From Name Empty';
        }
        if (!$errCode) {
            foreach ($to as $t) {
                if (!filter_var($t, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $emailParams = (new CVendor_MailerSend_Helpers_Builder_EmailParams())
                    ->setFrom($from)
                    ->setFromName($fromName)
                    ->setRecipients([
                        new CVendor_MailerSend_Helpers_Builder_Recipient($t, '')
                    ])
                    ->setSubject($subject)
                    ->setHtml($body);

                // Attachments
                if (!empty($attachments)) {
                    $mailAttachments = [];

                    foreach ($attachments as $attachment) {
                        $filePath = carr::get($attachment, 'path');

                        if (empty($filePath) || !file_exists($filePath)) {
                            continue;
                        }
                        $fileName = carr::get($attachment, 'filename', basename($filePath));
                        $fileType = carr::get($attachment, 'type', mime_content_type($filePath));

                        $mailAttachments[] = ['content' => base64_encode(file_get_contents($filePath)),
                            'filename' => $fileName,
                            'disposition' => 'attachment',
                            'type' => $fileType,
                        ];
                    }

                    if (!empty($mailAttachments)) {
                        $emailParams->setAttachments($mailAttachments);
                    }
                }

                $bulkEmailParams[] = $emailParams;
            }
        }
        if (!$errCode && !$bulkEmailParams) {
            $errCode++;
            $errMessage = 'No Recipients';
        }
        if (!$errCode) {
            //Endpoint /bulk-email butuh tier akun tersendiri di MailerSend --
            //akun tanpa tier itu ditolak 403 "Your plan doesn't allow to send
            //bulk emails" untuk SATU pun penerima. Satu penerima (kasus paling
            //umum: email transaksional) karena itu lewat endpoint /email biasa,
            //yang tersedia di semua tier; kirim ke banyak penerima sekaligus
            //tetap lewat /bulk-email seperti sebelumnya, jadi tidak ada
            //perubahan perilaku untuk pemanggil yang memang mengandalkan bulk.
            if (count($bulkEmailParams) === 1) {
                $response = $mailersend->email->send($bulkEmailParams[0]);
            } else {
                $response = $mailersend->bulkEmail->send($bulkEmailParams);
            }
            $statusCode = carr::get($response, 'status_code');
            if ($statusCode != 202) {
                $responseBody = carr::get($response, 'body', []);

                throw new Exception('Fail to send mail, API Response:(' . $statusCode . ')' . json_encode($responseBody));
            }
        } else {
            $response = [
                'errCode' => $errCode,
                'errMessage' => $errMessage,
            ];
        }

        return $response;
    }
}
