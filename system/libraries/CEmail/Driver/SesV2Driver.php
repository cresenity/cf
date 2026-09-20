<?php
use Aws\SesV2\SesV2Client;
use Aws\Exception\AwsException;

class CEmail_Driver_SesV2Driver extends CEmail_DriverAbstract {
    protected $ses;

    public function __construct(CEmail_Config $config) {
        parent::__construct($config);
        $this->ses = $this->createSesClient();
    }

    protected function createSesClient() {
        $sesConfig = [];
        $sesOptions = [];
        $key = $this->config->get('key', CF::config('vendor.ses.key')) ?: $this->config->getUsername();
        $secret = $this->config->get('secret', CF::config('vendor.ses.secret')) ?: $this->config->getPassword();
        $region = $this->config->get('region', $this->config->get('ses_region', CF::config('vendor.ses.region'))) ?: 'ap-southeast-1';
        $sesConfig = [
            'credentials' => [
                'key' => $key,
                'secret' => $secret,

            ],
            'region' => $region,
            'version' => 'latest',
        ];

        $sesClient = new SesV2Client($sesConfig, $sesOptions);

        return $sesClient;
    }

    public function send($to, $subject, $body, $options = []) {
        $from = carr::get($options, 'from', $this->config->getFrom());
        $fromName = carr::get($options, 'from_name', $this->config->getFromName());
        if (strlen($fromName) > 0) {
            $from = $fromName . ' <' . $from . '>';
        }
        $attachments = carr::get($options, 'attachments', []);
        $replyTo = carr::get($options, 'replyTo', '');
        $cc = carr::get($options, 'cc', []);
        $bcc = carr::get($options, 'bcc', []);
        $configurationSetName = c::get($options, 'configurationSetName');
        $result = null;
        $options = [];
        // $options['Tags'][] = ['Name' => 'subject', 'Value' => $subject];
        // $options['Tags'][] = ['Name' => 'From', 'Value' => $from];
        CDebug::variable('originalTo', $to);
        $destinations['ToAddresses'] = [];
        if (is_array($to) && count($to) > 0) {
            foreach ($to as $toItem) {
                $toName = '';
                $toEmail = $toItem;
                if (is_array($toItem)) {
                    $toName = carr::get($toItem, 'toName');
                    $toEmail = carr::get($toItem, 'toEmail');
                }
                $toAddress = $toEmail;
                if ($toName) {
                    $toAddress = $toName . ' <' . $toEmail . '>';
                }
                $destinations['ToAddresses'][] = $toAddress;
            }
        }

        if ($cc && count($cc) > 0) {
            $destinations['CcAddresses'] = $cc;
        }
        if ($bcc && count($bcc) > 0) {
            $destinations['BccAddresses'] = $bcc;
        }

        // if (is_array($to)) {
        //     $to = implode(',', $to);
        // }
        $toAddresses = carr::get($destinations, 'ToAddresses');
        if (is_array($toAddresses) && count($toAddresses) > 0) {
            $to = implode(',', $toAddresses);
        }
        CDebug::variable('manipulatedTo', $to);

        $rawMessage = $this->generateRawMessageData($from, $to, $subject, $body, $attachments, $options);

        $content = [
            'Raw' => [
                'Data' => $rawMessage,
            ],
        ];

        try {
            if (strlen($configurationSetName) > 0) {
                $options['ConfigurationSetName'] = $configurationSetName;
            }
            $options['FromEmailAddress'] = $from;
            $options['Destinations'] = $destinations;
            $options['Content'] = $content;
            if ($replyTo) {
                $options['ReplyToAddresses'] = [$replyTo];
            }

            $result = $this->ses->sendEmail($options);
        } catch (AwsException $e) {
            $reason = $e->getAwsErrorMessage() ?? $e->getMessage();

            throw new CEmail_Exception_EmailSendingFailedException(
                sprintf('Request to AWS SES API failed. Reason: %s.', $reason),
                is_int($e->getCode()) ? $e->getCode() : 0,
                $e
            );
        }

        return $result;
    }

    public function generateRawMessageData($from, $to, $subject, $body, $attachments, $options) {
        $replyTo = carr::get($options, 'replyTo', '');
        $cc = carr::get($options, 'cc', []);
        $bcc = carr::get($options, 'bcc', []);
        $boundary = md5(time());

        $message = "To: $to\n";
        $message .= "From: $from\n";

        // Tambahkan Cc
        if (!empty($cc)) {
            $message .= 'Cc: ' . implode(',', $cc) . "\n";
        }

        // Tambahkan Bcc
        if (!empty($bcc)) {
            $message .= 'Bcc: ' . implode(',', $bcc) . "\n";
        }

        // Tambahkan Reply-To
        if (!empty($replyTo)) {
            $message .= "Reply-To: $replyTo\n";
        }

        $message .= "Subject: $subject\n";
        $message .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        $message .= "\n\n";

        // Plain text part (optional)
        $message .= "--$boundary\n";
        $message .= 'Content-Type: multipart/alternative; boundary="alternative_boundary"';
        $message .= "\n\n";

        // Plain text body
        $message .= "--alternative_boundary\n";
        $message .= 'Content-Type: text/plain; charset=UTF-8';
        $message .= "\n";
        $message .= "\n";

        // HTML body
        $message .= "--alternative_boundary\n";
        $message .= 'Content-Type: text/html; charset=UTF-8';
        $message .= "\n";
        $message .= "\n";
        $message .= "$body\n";

        $message .= "--alternative_boundary--\n\n";

        // Bagian lampiran
        foreach ($attachments as $attachment) {
            $message .= "--$boundary\n";

            // Ganti ini dengan Content-Type yang sesuai dengan lampiran
            $message .= 'Content-Type: ' . $attachment['type'] . '; name="' . $attachment['filename'] . '"';
            $message .= "\n";
            $message .= 'Content-Description: ' . $attachment['filename'] . '';
            $message .= "\n";
            $message .= 'Content-Disposition: attachment; filename="' . $attachment['filename'] . '"';
            $message .= "\n";
            $message .= 'Content-Transfer-Encoding: base64';
            $message .= "\n";
            $message .= "\n";
            $message .= base64_encode(file_get_contents($attachment['path']));
            $message .= "\n";
        }

        $message .= "--$boundary--\n";

        return $message;
    }
}
