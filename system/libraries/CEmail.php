<?php

class CEmail {
    /**
     * @var null|CEmail_Testing_MailFake
     */
    protected static $fake;

    /**
     * @return \CEmail_Builder
     */
    public static function builder() {
        return CEmail_Builder::instance();
    }

    /**
     * Kerangka email bersama; kelas dari `email.template.class` (bawaan CEmail_Builder_Template_DefaultTemplate).
     *
     * @param array $options menimpa config email.template
     *
     * @throws CEmail_Builder_Exception
     *
     * @return CEmail_Builder_Template
     */
    public static function template(array $options = []) {
        $class = isset($options['class']) ? $options['class'] : CF::config('email.template.class');
        if (strlen((string) $class) == 0) {
            $class = CEmail_Builder_Template_DefaultTemplate::class;
        }
        if (!is_a($class, CEmail_Builder_Template::class, true)) {
            throw new CEmail_Builder_Exception('email.template.class harus turunan CEmail_Builder_Template: ' . $class);
        }

        return new $class($options);
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
     * Mulai kirim Mailable ke penerima: CEmail::to($users)->send($mailable).
     *
     * @param mixed $users
     *
     * @return CEmail_PendingMail
     */
    public static function to($users) {
        return static::mailer()->to($users);
    }

    /**
     * @param mixed $users
     *
     * @return CEmail_PendingMail
     */
    public static function cc($users) {
        return static::mailer()->cc($users);
    }

    /**
     * @param mixed $users
     *
     * @return CEmail_PendingMail
     */
    public static function bcc($users) {
        return static::mailer()->bcc($users);
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
     * @return CEmail_MailManager|CEmail_Testing_MailFake
     */
    public static function manager() {
        return static::$fake ?: CEmail_MailManager::instance();
    }

    /**
     * Ganti manager dengan fake yang hanya mencatat; dipakai di test bersama assertSent() dkk.
     *
     * @return CEmail_Testing_MailFake
     */
    public static function fake() {
        static::$fake = new CEmail_Testing_MailFake();
        CEmail_Sender_MailerDriver::forgetMailers();

        return static::$fake;
    }

    /**
     * @return void
     */
    public static function forgetFake() {
        static::$fake = null;
        CEmail_Sender_MailerDriver::forgetMailers();
    }

    /**
     * @return bool
     */
    public static function hasFake() {
        return static::$fake !== null;
    }

    /**
     * @throws LogicException
     *
     * @return CEmail_Testing_MailFake
     */
    protected static function activeFake() {
        if (static::$fake === null) {
            throw new LogicException('CEmail::fake() belum dipanggil; assertion email hanya tersedia setelah fake aktif.');
        }

        return static::$fake;
    }

    /**
     * @param string|Closure    $mailable
     * @param null|callable|int $callback
     *
     * @return void
     */
    public static function assertSent($mailable, $callback = null) {
        static::activeFake()->assertSent($mailable, $callback);
    }

    /**
     * @param string|Closure $mailable
     * @param null|callable  $callback
     *
     * @return void
     */
    public static function assertNotSent($mailable, $callback = null) {
        static::activeFake()->assertNotSent($mailable, $callback);
    }

    /**
     * @return void
     */
    public static function assertNothingSent() {
        static::activeFake()->assertNothingSent();
    }

    /**
     * @param string|Closure    $mailable
     * @param null|callable|int $callback
     *
     * @return void
     */
    public static function assertQueued($mailable, $callback = null) {
        static::activeFake()->assertQueued($mailable, $callback);
    }

    /**
     * @param string|Closure $mailable
     * @param null|callable  $callback
     *
     * @return void
     */
    public static function assertNotQueued($mailable, $callback = null) {
        static::activeFake()->assertNotQueued($mailable, $callback);
    }

    /**
     * @return void
     */
    public static function assertNothingQueued() {
        static::activeFake()->assertNothingQueued();
    }

    /**
     * @param int $count
     *
     * @return void
     */
    public static function assertSentCount($count) {
        static::activeFake()->assertSentCount($count);
    }

    /**
     * @param int $count
     *
     * @return void
     */
    public static function assertQueuedCount($count) {
        static::activeFake()->assertQueuedCount($count);
    }

    /**
     * @param int $count
     *
     * @return void
     */
    public static function assertOutgoingCount($count) {
        static::activeFake()->assertOutgoingCount($count);
    }

    /**
     * @return void
     */
    public static function assertNothingOutgoing() {
        static::activeFake()->assertNothingOutgoing();
    }

    /**
     * @param null|callable|int $callback
     *
     * @return void
     */
    public static function assertLegacySent($callback = null) {
        static::activeFake()->assertLegacySent($callback);
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
