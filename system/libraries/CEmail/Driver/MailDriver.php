<?php

/**
 * @deprecated 1.9 jalur pengirim lama; pakai CEmail::mailer() dengan transport `mail` (atau nyalakan `email.legacy_sender_via_mailer`)
 */
class CEmail_Driver_MailDriver extends CEmail_DriverAbstract {
    public function send(array $to, $subject, $body, $options = []) {
        $addresses = $this->formatAddresses($to);
        $returnPath = carr::get($options, 'returnPath', carr::get($options, 'from'));
        $headers = [];

        foreach (['cc' => 'Cc', 'bcc' => 'Bcc', 'reply_to' => 'Reply-To'] as $key => $headerKey) {
            $value = $this->formatAddresses(carr::get($options, $key, []));
            if (!empty($value)) {
                $headers[$headerKey] = $value;
            }
        }
        $response = @mail($addresses, $subject, $body, $headers, '-oi -f ' . $returnPath);
        if ($response === false) {
            $lastErrorMessage = carr::get(error_get_last(), 'message');

            throw new \CEmail_Exception_EmailSendingFailedException('Failed sending email:' . ($lastErrorMessage ?: 'unknown'));
        }

        return $response;
    }
}
