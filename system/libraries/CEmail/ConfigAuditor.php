<?php

/**
 * Pemeriksa baca-saja untuk konfigurasi email jalur lama (app.smtp_*, app.email.*, MAIL_MAILER).
 * Tidak mengubah apa pun; dipakai perintah `phpcf email:check`.
 */
class CEmail_ConfigAuditor {
    const LEVEL_WARNING = 'warning';

    const LEVEL_NOTICE = 'notice';

    /**
     * @param array $settings smtp_host, smtp_username, smtp_password, smtp_from, mail_mailer, domain, sender_call_files
     *
     * @return array daftar ['level' => warning|notice, 'key' => string, 'message' => string]
     */
    public function audit(array $settings) {
        $findings = [];
        $host = trim((string) carr::get($settings, 'smtp_host'));
        if ($host === '') {
            return $findings;
        }

        $map = CEmail_Config::driverForSmtpHost($host);
        $driver = $map ?: 'smtp';

        $conflict = CEmail_Config::mailerEnvConflict($driver, $host, carr::get($settings, 'mail_mailer'));
        if ($conflict !== null) {
            $findings[] = $this->finding(static::LEVEL_WARNING, 'MAIL_MAILER', $conflict);
        }

        if ($map !== null && c::blank(carr::get($settings, 'smtp_password'))) {
            $findings[] = $this->finding(
                static::LEVEL_WARNING,
                'smtp_password',
                "smtp_host '" . $host . "' dikenali sebagai '" . $driver . "' tetapi smtp_password kosong; pengiriman akan ditolak penyedia."
            );
        }

        $from = (string) carr::get($settings, 'smtp_from');
        if ($from !== '' && !$this->fromMatchesDomain($from, (string) carr::get($settings, 'domain'))) {
            $findings[] = $this->finding(
                static::LEVEL_NOTICE,
                'smtp_from',
                "alamat pengirim '" . $from . "' berdomain berbeda dari domain app '" . carr::get($settings, 'domain') . "'; periksa apakah ini sisa salinan dari app lain."
            );
        }

        $files = (int) carr::get($settings, 'sender_call_files', 0);
        if ($files > 0) {
            $findings[] = $this->finding(
                static::LEVEL_NOTICE,
                'CEmail::sender()',
                $files . " berkas app memanggil CEmail::sender(); jalur ini memilih provider dari 'driver' atau smtp_host, bukan MAIL_MAILER."
            );
        }

        return $findings;
    }

    /**
     * @param string $level
     * @param string $key
     * @param string $message
     *
     * @return array
     */
    protected function finding($level, $key, $message) {
        return ['level' => $level, 'key' => $key, 'message' => $message];
    }

    /**
     * Domain alamat pengirim sama dengan, induk dari, atau subdomain dari domain app; domain app kosong dianggap cocok.
     *
     * @param string $from
     * @param string $domain
     *
     * @return bool
     */
    protected function fromMatchesDomain($from, $domain) {
        $domain = strtolower(preg_replace('/^www\./i', '', trim($domain)));
        if ($domain === '' || strpos($from, '@') === false) {
            return true;
        }
        $fromDomain = strtolower(trim(substr($from, strrpos($from, '@') + 1), "> \t"));

        return $fromDomain === $domain
            || substr($domain, -strlen('.' . $fromDomain)) === '.' . $fromDomain
            || substr($fromDomain, -strlen('.' . $domain)) === '.' . $domain;
    }
}
