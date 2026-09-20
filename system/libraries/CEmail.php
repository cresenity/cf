<?php

class CEmail {
    /**
     * @return \CEmail_Builder
     */
    public static function builder() {
        return CEmail_Builder::instance();
    }

    /**
     * @param array $config
     *
     * @return CEmail_Sender
     *
     * @deprecated 1.9 pakai CEmail::mailer($name) (config `email.mailers.<name>`); sender() tetap berjalan dan bisa dialihkan ke mailer lewat `email.legacy_sender_via_mailer`
     */
    public static function sender(array $config = []) {
        CF::deprecated('CEmail::sender', 'CEmail::mailer()', '1.9');

        return new CEmail_Sender($config);
    }

    /**
     * @param string $email
     *
     * @return bool
     */
    public static function isValid($email) {
        $checker = new CEmail_Checker();

        return $checker->isValid($email);
    }

    /**
     * @param string $name
     *
     * @return CEmail_Mailer
     */
    public static function mailer($name = '') {
        return self::manager()->mailer($name);
    }

    /**
     * @return CEmail_MailManager
     */
    public static function manager() {
        return CEmail_MailManager::instance();
    }

    /**
     * @return CEmail_Client
     */
    public static function client() {
        return new CEmail_Client();
    }

    public static function markdown() {
        return new CEmail_Markdown([
            'theme' => CF::config('email.markdown.theme', 'default'),
            'paths' => CF::config('email.markdown.paths', []),
        ]);
    }

    public static function send($view, array $data = [], $callback = null) {
        return self::mailer()->send($view, $data, $callback);
    }
}
