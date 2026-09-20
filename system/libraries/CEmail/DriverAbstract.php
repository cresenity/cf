<?php

/**
 * @deprecated 1.9 dasar driver lama; formatAddress()/emailAddresses() tetap dipakai CEmail_Sender_MailerDriver
 */
abstract class CEmail_DriverAbstract implements CEmail_DriverInterface {
    /**
     * Email Config.
     *
     * @var CEmail_Config
     */
    protected $config;

    /**
     * @param CEmail_Config $config
     */
    public function __construct(CEmail_Config $config) {
        $this->config = $config;
    }

    /**
     * Get Email Config.
     *
     * @return CEmail_Config
     */
    public function getConfig() {
        return $this->config;
    }

    public static function formatAddresses(array $items) {
        $addresses = static::arrayAddresses($items);
        $return = [];
        foreach ($addresses as $recipient) {
            $return[] = static::formatAddress($recipient);
        }

        return implode(', ', $return);
    }

    /**
     * Satu alamat untuk header: `"Nama" <email>` (nama non-ASCII di-encode) atau email saja.
     *
     * @param array|string $recipient
     *
     * @return string
     */
    public static function formatAddress($recipient) {
        if (!is_array($recipient)) {
            return (string) $recipient;
        }
        $email = (string) carr::get($recipient, 'email');
        $name = trim((string) carr::get($recipient, 'name'));
        if ($name === '') {
            return $email;
        }
        if (preg_match('/[^\x20-\x7e]/', $name)) {
            $name = mb_encode_mimeheader($name, 'utf-8', 'B', "\r\n");
        } else {
            $name = '"' . addcslashes($name, '"\\') . '"';
        }

        return $name . ' <' . $email . '>';
    }

    /**
     * Daftar alamat email saja (tanpa nama), untuk RCPT TO / envelope.
     *
     * @param array $items
     *
     * @return string[]
     */
    public static function emailAddresses(array $items) {
        return array_values(array_filter(array_map(function ($item) {
            return is_array($item) ? (string) carr::get($item, 'email') : (string) $item;
        }, static::arrayAddresses($items)), 'strlen'));
    }

    protected static function arrayAddresses(array $items, $emailOnly = false) {
        $addresses = [];
        foreach ($items as $item) {
            $toName = '';
            $email = $item;
            if (is_array($item)) {
                if ($emailOnly) {
                    $email = carr::get($item, 'email', carr::get($item, 'toEmail'));
                } else {
                    $email = [
                        'email' => carr::get($item, 'email', carr::get($item, 'toEmail')),
                        'name' => carr::get($item, 'name', carr::get($item, 'toName')),

                    ];
                    $addresses[] = $email;
                }
            }
            if (is_string($email) && strlen($email) > 0) {
                $addresses[] = $email;
            }
        }

        return $addresses;
    }
}
