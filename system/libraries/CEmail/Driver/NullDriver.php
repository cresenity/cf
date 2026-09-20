<?php

/**
 * @deprecated 1.9 jalur pengirim lama; pakai CEmail::mailer() dengan transport `array` (atau nyalakan `email.legacy_sender_via_mailer`)
 */
class CEmail_Driver_NullDriver extends CEmail_DriverAbstract {
    public function send(array $to, $subject, $body, $options = []) {
        return null;
    }
}
