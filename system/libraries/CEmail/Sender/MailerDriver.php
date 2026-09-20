<?php

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Driver jalur lama (`CEmail::sender()->send()`) yang mengirim lewat CEmail_Mailer/Symfony Mailer.
 * Aktif bila config `email.legacy_sender_via_mailer` true; menerima $to/$options dalam semua bentuk
 * yang dipakai app dan mengembalikan CEmail_SentMessage (false bila event MessageSending memveto).
 */
class CEmail_Sender_MailerDriver extends CEmail_DriverAbstract {
    /**
     * Mailer per konfigurasi, supaya transport SMTP tidak dibangun ulang tiap kirim.
     *
     * @var CEmail_Mailer[]
     */
    protected static $mailers = [];

    /**
     * @var CEmail_Mailer
     */
    protected $mailer;

    /**
     * @param CEmail_Config $config
     */
    public function __construct(CEmail_Config $config) {
        parent::__construct($config);
        $mailerConfig = $config->toMailerConfig();
        $key = md5(serialize($mailerConfig));
        if (!isset(static::$mailers[$key])) {
            static::$mailers[$key] = CEmail::manager()->build($mailerConfig);
        }
        $this->mailer = static::$mailers[$key];
    }

    /**
     * @return CEmail_Mailer
     */
    public function getMailer() {
        return $this->mailer;
    }

    /**
     * Buang mailer yang di-cache (untuk test atau setelah config berubah di runtime).
     *
     * @return void
     */
    public static function forgetMailers() {
        static::$mailers = [];
    }

    /**
     * @inheritdoc
     */
    public function send(array $to, $subject, $body, $options = []) {
        $callback = function (CEmail_Message $message) use ($to, $subject, $options) {
            $this->fill($message, $to, $subject, $options);
        };
        $type = (string) carr::get($options, 'type', 'html');

        try {
            $sent = cstr::startsWith($type, 'plain')
                ? $this->mailer->raw((string) $body, $callback)
                : $this->mailer->html((string) $body, $callback);
        } catch (TransportExceptionInterface $e) {
            throw $this->translateException($e);
        }

        return $sent === null ? false : $sent;
    }

    /**
     * Terapkan $to dan $options jalur lama ke pesan.
     *
     * @param CEmail_Message $message
     * @param array          $to
     * @param string         $subject
     * @param array          $options
     *
     * @return void
     */
    protected function fill(CEmail_Message $message, array $to, $subject, array $options) {
        $message->subject((string) $subject);
        $from = CEmail_Config::resolveFrom($options, $this->config);
        if ($from) {
            $message->from($from, CEmail_Config::resolveFromName($options, $this->config));
        }
        foreach (CEmail_Address::normalize($to) as $address) {
            $message->to($address['email'], $address['name']);
        }
        foreach (['cc', 'bcc'] as $key) {
            foreach (CEmail_Address::normalize(carr::get($options, $key)) as $address) {
                $message->{$key}($address['email'], $address['name']);
            }
        }
        foreach (CEmail_Address::normalize(carr::get($options, 'reply_to', carr::get($options, 'replyTo'))) as $address) {
            $message->replyTo($address['email'], $address['name']);
        }
        if (carr::get($options, 'returnPath')) {
            $message->returnPath(carr::get($options, 'returnPath'));
        }
        if (carr::get($options, 'priority')) {
            $message->priority((int) carr::get($options, 'priority'));
        }
        foreach ((array) carr::get($options, 'headers', []) as $name => $value) {
            if (is_string($name) && $value !== null) {
                $message->getHeaders()->addTextHeader($name, (string) $value);
            }
        }
        foreach (CEmail_Attachment::fromLegacy(carr::get($options, 'attachments', carr::get($options, 'attachment'))) as $attachment) {
            $message->attach($attachment);
        }
    }

    /**
     * Exception Symfony → kelas exception jalur lama supaya `catch` di app tetap mengena.
     *
     * @param TransportExceptionInterface $e
     *
     * @return Exception
     */
    protected function translateException(TransportExceptionInterface $e) {
        $message = $e->getMessage();
        if (preg_match('/authenticat|535|AUTH/i', $message)) {
            return new CEmail_Exception_SmtpAuthenticationFailedException($message, 0, $e);
        }
        if (preg_match('/connection|connect to|timed out|timeout|resolve/i', $message)) {
            return new CEmail_Exception_SmtpConnectionException($message, 0, $e);
        }

        return new CEmail_Exception_EmailSendingFailedException($message, 0, $e);
    }
}
