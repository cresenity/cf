<?php
use Aws\Ses\SesClient;
use Aws\Exception\AwsException;

class CEmail_Driver_SesDriver extends CEmail_DriverAbstract {
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
        $region = $this->config->get('region', $this->config->get('ses_region', CF::config('vendor.ses.region'))) ?: ($this->config->get('smtp_region') ?: 'ap-southeast-1');
        $sesConfig = [
            'credentials' => [
                'key' => $key,
                'secret' => $secret,

            ],
            'region' => $region,
            'version' => 'latest',
        ];

        $sesClient = new SesClient($sesConfig, $sesOptions);

        return $sesClient;
    }

    public function getQuota() {
        $quota = $this->ses->getSendQuota();
        $data = [];
        $data['Max24HourSend'] = $quota->get('Max24HourSend');

        return $data;
    }

    public function verifyEmailAddress($email) {
        $result = $this->ses->verifyEmailAddress([
            'EmailAddress' => $email
        ]);
        cdbg::dd($result);
    }

    public function getVerifiedEmailAddresses() {
        $emails = $this->ses->listVerifiedEmailAddresses();

        $data = [];
        $data['VerifiedEmailAddresses'] = $emails->get('VerifiedEmailAddresses');

        return $data;
    }

    public function getListIdentitiesDomain() {
        $client = $this->ses;

        $result = $client->listIdentities([
            'IdentityType' => 'Domain', // atau 'Domain', atau kosong untuk semua
            'MaxItems'     => 50,
        ]);

        $identities = $result->get('Identities') ?: [];
        return $identities;
    }

    public function send2(array $to, $subject, $body, $options = []) {
        // $this->verifyEmailAddress('adamwsw8@gmail.com');
        $from = carr::get($options, 'from', $this->config->getFrom());
        // $from = 'adamwsw8@gmail.com';
        $fromName = carr::get($options, 'from_name', $this->config->getFromName());
        $attachments = carr::get($options, 'attachments', []);
        $replyTo = carr::get($options, 'replyTo', '');
        $cc = carr::get($options, 'cc', []);
        $bcc = carr::get($options, 'bcc', []);
        $configurationSetName = c::geT($options, 'configurationSetName', '');
        $result = null;
        $options = [];
        // $options['Tags'][] = ['Name' => 'subject', 'Value' => $subject];
        // $options['Tags'][] = ['Name' => 'From', 'Value' => $from];
        $source = $from;

        if ($fromName) {
            $source = $fromName . ' <' . $from . '>';
        }
        $destinations['ToAddresses'] = [];
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

        if ($cc && count($cc) > 0) {
            $destinations['CcAddresses'] = $cc;
        }
        if ($bcc && count($bcc) > 0) {
            $destinations['BccAddresses'] = $bcc;
        }
        $content = [

            'Subject' => [
                'Data' => $subject,
            ],
            'Body' => [
                'Html' => [
                    'Data' => $body,
                ],
                'Text' => [
                    'Data' => strip_tags($body),
                ],
            ],

        ];
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
            $fileAttachment = '';
            if (strlen($disk) > 0) {
                $diskObject = CStorage::instance()->disk($disk);
                $fileAttachment = $diskObject->get($path);
            } else {
                $fileAttachment = file_get_contents($path);
            }

            if (!isset($content['Attachments'])) {
                $content['Attachments'] = [];
            }
            $content['Attachments'][] = [
                'Data' => $fileAttachment,
                'FileName' => $attachmentFilename, // Nama file lampiran yang akan terlihat di email
                'ContentType' => $type, // Ganti sesuai dengan jenis file lampiran Anda
            ];
        }

        try {
            $options = [];
            $options['Source'] = $source;
            $options['Destination'] = $destinations;
            $options['Message'] = $content;
            if (strlen($configurationSetName) > 0) {
                $options['ConfigurationSetName'] = $configurationSetName;
            }

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
        $messageId = $result->get('MessageId');

        return $result;
    }

    public function send(array $to, $subject, $body, $options = []) {
        $from = carr::get($options, 'from', $this->config->getFrom());
        $fromName = carr::get($options, 'from_name', $this->config->getFromName());
        $attachments = carr::get($options, 'attachments', []);
        $configurationSetName = c::get($options, 'configurationSetName', '');
        $result = null;

        if (is_array($to)) {
            $to = implode(',', $to);
        }
        $rawMessage = $this->generateRawMessageData($from, $to, $subject, $body, $attachments, $options);
        $options = [];

        try {
            $options = [];
            $options['Source'] = $from;
            $options['Destinations'] = [$to];
            $options['RawMessage'] = [
                'Data' => $rawMessage,
            ];
            if (strlen($configurationSetName) > 0) {
                $options['ConfigurationSetName'] = $configurationSetName;
            }
            $result = $this->ses->sendRawEmail($options);
        } catch (AwsException $e) {
            $reason = $e->getAwsErrorMessage() ?? $e->getMessage();

            throw new CEmail_Exception_EmailSendingFailedException(
                sprintf('Request to AWS SES API failed. Reason: %s.', $reason),
                is_int($e->getCode()) ? $e->getCode() : 0,
                $e
            );
        }
        $messageId = $result->get('MessageId');

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

        // // Bagian teks
        $message .= "--$boundary\n";
        $message .= 'Content-Type: multipart/alternative; boundary="alternative_boundary"';
        $message .= "\n\n";

        $message .= "--alternative_boundary\n";
        $message .= 'Content-Type: text/plain; charset=us-ascii';
        $message .= "\n";
        $message .= "\n";

        $message .= "--alternative_boundary\n";
        $message .= 'Content-Type: text/html; charset=us-ascii';
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
