<?php

class CEmail_Config {
    use CTrait_HasOptions;

    /**
     * @var string
     */
    protected $driver;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $host;

    /**
     * @var string
     */
    protected $port;

    /**
     * @var string
     */
    protected $secure;

    /**
     * @var string
     */
    protected $protocol;

    /**
     * @var string
     */
    protected $from;

    /**
     * @var string
     */
    protected $fromName;

    protected static $smtpHostToDriverMap = [
        'smtp.sendgrid.net' => 'sendgrid',
        'smtp.mailgun.org' => 'mailgun',
        'smtp.elasticemail.com' => 'elasticemail',
        'smtp25.elasticemail.com' => 'elasticemail',
        'smtp.postmarkapp.com' => 'postmarkapp',
        'smtp.amazonaws.com' => 'ses',
        'smtp.mailersend.com' => 'mailersend',
        'smtp-relay.brevo.com' => 'brevo',
        'smtp-relay.sendinblue.com' => 'brevo',
    ];

    public function __construct($options = []) {
        $options = $this->reformatOptions($options);
        $this->options = $options;
        $this->driver = carr::get($options, 'driver');
        $this->username = carr::get($options, 'username');
        $this->password = carr::get($options, 'password');
        $this->host = carr::get($options, 'host');
        $this->port = carr::get($options, 'port');
        $this->secure = carr::get($options, 'secure');
        $this->protocol = carr::get($options, 'protocol', 'tcp');
        $this->from = carr::get($options, 'from');
        $this->fromName = carr::get($options, 'from_name');
    }

    public function reformatOptions($config) {
        $config = $this->mergeWithDefaultConfig($config);

        $isLegacyOptions = !carr::get($config, 'driver');

        $newConfig = $config;

        if ($isLegacyOptions) {
            $smtpHost = carr::get($config, 'host', carr::get($config, 'smtp_host'));
            if ($smtpHost == null) {
                throw new CEmail_Exception_InvalidConfigException('Konfigurasi email tidak punya driver maupun host SMTP; isi salah satu dari: driver, host/smtp_host, app.email.host, app.smtp_host');
            }

            $driver = carr::get(static::$smtpHostToDriverMap, $smtpHost, 'smtp');

            $newConfig = [];
            $newConfig['driver'] = $driver;
            $newConfig['username'] = carr::get($config, 'username', carr::get($config, 'smtp_username'));
            $newConfig['password'] = carr::get($config, 'password', carr::get($config, 'smtp_password'));
            $newConfig['from'] = carr::get($config, 'from', carr::get($config, 'smtp_from'));
            $newConfig['from_name'] = carr::get($config, 'from_name', carr::get($config, 'smtp_from_name'));
            $newConfig['secure'] = carr::get($config, 'secure', carr::get($config, 'smtp_secure'));
            if ($driver == 'smtp') {
                $newConfig['host'] = carr::get($config, 'host', carr::get($config, 'smtp_host'));
                $newConfig['port'] = carr::get($config, 'port', carr::get($config, 'smtp_port'));
            }
        }

        return $newConfig;
    }

    public function mergeWithDefaultConfig($config) {
        if (!isset($config['from']) || c::blank($config['from'])) {
            $config['from'] = static::resolveFrom($config);
        }

        if (!isset($config['from_name']) || c::blank($config['from_name'])) {
            $config['from_name'] = static::resolveFromName($config);
        }
        if (!isset($config['host']) || c::blank($config['host'])) {
            $config['host'] = carr::get($config, 'smtp_host', CF::config('app.email.host', CF::config('app.smtp_host')));
        }
        if (!isset($config['username']) || c::blank($config['username'])) {
            $config['username'] = carr::get($config, 'smtp_username', CF::config('app.email.username', CF::config('app.smtp_username')));
        }
        if (!isset($config['password']) || c::blank($config['password'])) {
            $config['password'] = carr::get($config, 'smtp_password', CF::config('app.email.password', CF::config('app.smtp_password')));
        }

        if (!isset($config['secure']) || c::blank($config['secure'])) {
            $config['secure'] = carr::get($config, 'smtp_secure', CF::config('app.email.secure', CF::config('app.smtp_secure')), 'tls');
        }

        return $config;
    }

    /**
     * @return string
     */
    /**
     * Alamat pengirim: from → smtp_from → app.email.from → app.smtp_from. Satu-satunya urutan
     * yang dipakai Sender dan Config.
     *
     * @param array $options
     *
     * @return null|string
     */
    public static function resolveFrom(array $options) {
        foreach (['from', 'smtp_from'] as $key) {
            if (!c::blank(carr::get($options, $key))) {
                return carr::get($options, $key);
            }
        }

        return CF::config('app.email.from', CF::config('app.smtp_from'));
    }

    /**
     * Nama pengirim: from_name → smtp_from_name → app.email.from_name → app.smtp_from_name.
     *
     * @param array $options
     *
     * @return null|string
     */
    public static function resolveFromName(array $options) {
        foreach (['from_name', 'smtp_from_name'] as $key) {
            if (!c::blank(carr::get($options, $key))) {
                return carr::get($options, $key);
            }
        }

        return CF::config('app.email.from_name', CF::config('app.smtp_from_name'));
    }

    /**
     * Enkripsi SMTP yang dinormalkan dari nilai `secure` apa pun yang ditulis app:
     * tls/starttls → 'tls', ssl → 'ssl', false/'false'/none/'' → null.
     *
     * @return null|string
     */
    public function getEncryption() {
        $secure = strtolower(trim((string) $this->secure));
        if (in_array($secure, ['tls', 'starttls', 'true', '1'], true)) {
            return 'tls';
        }
        if ($secure === 'ssl') {
            return 'ssl';
        }

        return null;
    }

    public function getDriver() {
        return $this->driver;
    }

    /**
     * @return string
     */
    public function getUsername() {
        return $this->username;
    }

    public function getPassword() {
        return $this->password;
    }

    public function getHost() {
        return $this->host;
    }

    public function getPort() {
        return $this->port;
    }

    public function getSecure() {
        return $this->secure;
    }

    public function getProtocol() {
        return $this->protocol;
    }

    public function getFrom() {
        return $this->from;
    }

    public function getFromName() {
        return $this->fromName;
    }

    public function get($key, $default = null) {
        return carr::get($this->options, $key, $default);
    }
}
