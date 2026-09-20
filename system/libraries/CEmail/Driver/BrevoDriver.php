<?php

/**
 * Pengiriman lewat REST API Brevo.
 *
 * **Kunci API dan SMTP key Brevo dua kredensial berbeda.** Yang dipakai di
 * sini kunci API; SMTP key hanya sah untuk `smtp-relay.brevo.com` dan ditolak
 * REST API dengan 401. Karena `CEmail_Config::getPassword()` berisi kredensial
 * SMTP saat konfigurasinya berasal dari `SMTP_*`, kunci API dicari lebih dulu
 * dari konfigurasi mailer (`email.mailers.brevo.key`) lalu `vendor.brevo.api_key`,
 * dan `getPassword()` hanya dipakai sebagai jalan terakhir - berguna bagi
 * pemanggil yang memang mengisinya dengan kunci API.
 *
 * Tidak memakai SDK resmi di `system/vendor/Brevo`: SDK itu menuntut PHP 8.1
 * (enum, `new` di nilai bawaan parameter), sedangkan kerangka kerja ini
 * menyatakan PHP >= 7.4 dan sebagian vhost memang masih 7.4.
 *
 * @deprecated 1.9 jalur pengirim lama; pakai CEmail::mailer() dengan transport `brevo` (atau nyalakan `email.legacy_sender_via_mailer`)
 */
class CEmail_Driver_BrevoDriver extends CEmail_DriverAbstract {
    /**
     * @param array  $to
     * @param string $subject
     * @param string $body
     * @param array  $options
     *
     * @throws Exception
     *
     * @return array
     */
    public function send(array $to, $subject, $body, $options = []) {
        $from = carr::get($options, 'from', $this->config->getFrom());
        $fromName = carr::get($options, 'from_name', $this->config->getFromName());

        if (strlen((string) $from) === 0) {
            throw new Exception('Brevo: from empty');
        }

        $recipient = $this->addressList($to);

        if (count($recipient) === 0) {
            throw new Exception('Brevo: no recipients');
        }

        $sender = ['email' => $from];

        if (strlen((string) $fromName) > 0) {
            $sender['name'] = $fromName;
        }

        $payload = [
            'sender' => $sender,
            'to' => $recipient,
            'subject' => $subject,
            'htmlContent' => $body,
        ];

        foreach (['cc', 'bcc'] as $key) {
            $address = $this->addressList(carr::get($options, $key, []));

            if (count($address) > 0) {
                $payload[$key] = $address;
            }
        }

        $replyTo = $this->addressList(carr::get($options, 'reply_to', carr::get($options, 'replyTo', [])));

        if (count($replyTo) > 0) {
            //Brevo hanya menerima satu replyTo, bukan daftar.
            $payload['replyTo'] = $replyTo[0];
        }

        $attachment = $this->attachmentList(carr::get($options, 'attachments', []));

        if (count($attachment) > 0) {
            $payload['attachment'] = $attachment;
        }

        return CVendor_Brevo::transactionalEmail(['apiKey' => $this->apiKey()])->send($payload);
    }

    /**
     * @return string
     */
    protected function apiKey() {
        $key = (string) CF::config('email.mailers.brevo.key');

        if (strlen($key) === 0) {
            $key = (string) CF::config('vendor.brevo.api_key');
        }

        if (strlen($key) === 0) {
            $key = (string) $this->config->getPassword();
        }

        return $key;
    }

    /**
     * Menyeragamkan alamat jadi bentuk yang diminta Brevo.
     *
     * Menerima string, daftar string, atau daftar array ber-`email` - tiga
     * bentuk yang benar-benar dipakai pemanggil CEmail.
     *
     * @param mixed $address
     *
     * @return array
     */
    protected function addressList($address) {
        if ($address === null || $address === '') {
            return [];
        }

        if (is_string($address)) {
            $address = array_filter(array_map('trim', explode(',', $address)));
        }

        if (is_array($address) && isset($address['email'])) {
            $address = [$address];
        }

        $result = [];

        foreach ((array) $address as $item) {
            if (is_string($item) && filter_var(trim($item), FILTER_VALIDATE_EMAIL)) {
                $result[] = ['email' => trim($item)];

                continue;
            }

            $email = is_array($item) ? carr::get($item, 'email') : null;

            if (strlen((string) $email) > 0) {
                $entry = ['email' => $email];
                $name = carr::get($item, 'name');

                if (strlen((string) $name) > 0) {
                    $entry['name'] = $name;
                }

                $result[] = $entry;
            }
        }

        return $result;
    }

    /**
     * Lampiran, disandikan base64 seperti yang diminta Brevo.
     *
     * Berkas yang tidak ada dilewati, bukan menggagalkan seluruh kiriman -
     * sejalan dengan CEmail_Driver_MailersendDriver.
     *
     * @return array
     */
    protected function attachmentList(array $attachments) {
        $result = [];

        foreach ($attachments as $attachment) {
            $path = carr::get($attachment, 'path');

            if (strlen((string) $path) === 0 || !is_file($path)) {
                continue;
            }

            $result[] = [
                'content' => base64_encode(file_get_contents($path)),
                'name' => carr::get($attachment, 'filename', basename($path)),
            ];
        }

        return $result;
    }
}
