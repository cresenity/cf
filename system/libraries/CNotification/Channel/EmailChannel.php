<?php

class CNotification_Channel_EmailChannel extends CNotification_ChannelAbstract {
    /**
     * @param array $config
     */
    public function __construct($config = []) {
        parent::__construct($config);
        $this->channelName = 'Email';
    }

    /**
     * @param mixed $data
     * @param mixed $logNotificationModel
     *
     * @return mixed
     */
    protected function handleMessage($data, $logNotificationModel) {
        $mailer = carr::get($data, 'mailer');
        $to = carr::get($data, 'recipient');
        $subject = carr::get($data, 'subject');
        $message = carr::get($data, 'message');
        $attachment = carr::get($data, 'attachments', carr::get($data, 'attachment', []));
        $cc = carr::get($data, 'cc', []);
        $bcc = carr::get($data, 'bcc', []);
        $options = carr::get($data, 'options', []);
        $errCode = 0;
        $errMessage = '';
        if ($errCode == 0) {
            try {
                $options['cc'] = $cc;
                $options['bcc'] = $bcc;
                $options['attachments'] = $attachment;
                if ($mailer) {
                    $response = $this->sendThroughMailer($mailer, $to, $subject, $message, $options);
                } else {
                    $response = CEmail::sender($options)->send($to, $subject, $message, $options);
                }
            } catch (Exception $ex) {
                $errCode++;
                $errMessage = $ex->getMessage();
            }
        }
        if ($errCode > 0) {
            throw new CNotification_Exception($errMessage);
        }

        return $response;
    }

    /**
     * @return void
     */
    /**
     * Kirim record lewat mailer bernama (config email.mailers.<name>) dengan opsi record yang sama
     * seperti jalur CEmail::sender().
     *
     * @param string       $mailer
     * @param array|string $to
     * @param string       $subject
     * @param string       $message
     * @param array        $options
     *
     * @return null|CEmail_SentMessage
     */
    protected function sendThroughMailer($mailer, $to, $subject, $message, array $options) {
        return CEmail::mailer($mailer)->html($message, function (CEmail_Message $mail) use ($to, $subject, $options) {
            $mail->subject($subject);
            foreach (carr::wrap($to) as $recipient) {
                $mail->to(is_array($recipient) ? carr::get($recipient, 'email', carr::get($recipient, 'toEmail')) : $recipient, is_array($recipient) ? carr::get($recipient, 'name', carr::get($recipient, 'toName')) : null);
            }
            foreach (['cc', 'bcc'] as $key) {
                foreach (carr::wrap(carr::get($options, $key, [])) as $address) {
                    $mail->{$key}(is_array($address) ? carr::get($address, 'email') : $address, is_array($address) ? carr::get($address, 'name') : null);
                }
            }
            $from = CEmail_Config::resolveFrom($options);
            if ($from) {
                $mail->from($from, CEmail_Config::resolveFromName($options));
            }
            $replyTo = carr::get($options, 'reply_to', carr::get($options, 'replyTo'));
            if ($replyTo) {
                $mail->replyTo($replyTo);
            }
            foreach (carr::wrap(carr::get($options, 'attachments', [])) as $attachment) {
                if ($attachment instanceof CEmail_Attachment || $attachment instanceof CEmail_Contract_AttachableInterface) {
                    $mail->attach($attachment);
                } elseif (is_array($attachment) && isset($attachment['data'])) {
                    $mail->attachData($attachment['data'], carr::get($attachment, 'name', 'attachment'), array_filter(['mime' => carr::get($attachment, 'mime', carr::get($attachment, 'type'))]));
                } elseif (is_array($attachment) && isset($attachment['path'])) {
                    $mail->attach($attachment['path'], array_filter(['as' => carr::get($attachment, 'filename', carr::get($attachment, 'name')), 'mime' => carr::get($attachment, 'type', carr::get($attachment, 'mime'))]));
                } elseif (is_string($attachment) && $attachment !== '') {
                    $mail->attach($attachment);
                }
            }
        });
    }

    /**
     * @deprecated 1.9 tidak pernah berisi apa pun; akan dihapus
     */
    protected function sendEmail() {
    }
}
