<?php
use Aws\Ses\SesClient;
use Aws\SesV2\SesV2Client;
// use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\FailoverTransport;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkTransportFactory;

/**
 * @mixin \CEmail_Mailer
 */
class CEmail_MailManager implements CEmail_Contract_FactoryInterface {
    /**
     * /**
     * The array of resolved mailers.
     *
     * @var array
     */
    protected $mailers = [];

    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $customCreators = [];

    private static $instance;

    /**
     * @return CEmail_MailManager
     */
    public static function instance() {
        if (static::$instance == null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Create a new Mail manager instance.
     *
     * @return void
     */
    public function __construct() {
    }

    /**
     * Get a mailer instance by name.
     *
     * @param null|string $name
     *
     * @return \CEmail_Contract_MailerInterface
     */
    public function mailer($name = null) {
        $name = $name ?: $this->getDefaultDriver();

        return $this->mailers[$name] = $this->get($name);
    }

    /**
     * Get a mailer driver instance.
     *
     * @param null|string $driver
     *
     * @return \CEmail_Mailer
     */
    public function driver($driver = null) {
        return $this->mailer($driver);
    }

    /**
     * Attempt to get the mailer from the local cache.
     *
     * @param string $name
     *
     * @return \CEmail_Mailer
     */
    protected function get($name) {
        return $this->mailers[$name] ?? $this->resolve($name);
    }

    /**
     * Resolve the given mailer.
     *
     * @param string $name
     *
     * @throws \InvalidArgumentException
     *
     * @return \CEmail_Mailer
     */
    protected function resolve($name) {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Mailer [{$name}] is not defined.");
        }

        // Once we have created the mailer instance we will set a container instance
        // on the mailer. This allows us to resolve mailer classes via containers
        // for maximum testability on said classes instead of passing Closures.
        $mailer = $this->build(array_merge(['name' => $name], $config));

        // Next we will set all of the global addresses on this mailer, which allows
        // for easy unification of all "from" addresses as well as easy debugging
        // of sent messages since these will be sent to a single email address.
        foreach (['from', 'reply_to', 'to', 'return_path'] as $type) {
            $this->setGlobalAddress($mailer, $config, $type);
        }

        return $mailer;
    }

    /**
     * Build a new mailer instance.
     *
     * @param array $config
     *
     * @return \CEmail_Mailer
     */
    public function build($config) {
        $mailer = new CEmail_Mailer(
            $config['name'] ?? 'ondemand',
            $this->createSymfonyTransport($config),
        );

        // if ($this->app->bound('queue')) {
        //     $mailer->setQueue($this->app['queue']);
        // }
        $mailer->setQueue(CQueue::queuer());

        return $mailer;
    }

    /**
     * Create a new transport instance.
     *
     * @param array $config
     *
     * @throws \InvalidArgumentException
     *
     * @return \Symfony\Component\Mailer\Transport\TransportInterface
     */
    public function createSymfonyTransport(array $config) {
        // Here we will check if the "transport" key exists and if it doesn't we will
        // assume an application is still using the legacy mail configuration file
        // format and use the "mail.driver" configuration option instead for BC.
        $transport = carr::get($config, 'transport', CF::config('email.driver'));

        if (isset($this->customCreators[$transport])) {
            return call_user_func($this->customCreators[$transport], $config);
        }

        if (trim($transport ?? '') === '' || !method_exists($this, $method = 'create' . ucfirst($transport) . 'Transport')) {
            throw new InvalidArgumentException("Unsupported mail transport [{$transport}].");
        }

        return $this->{$method}($config);
    }

    /**
     * Create an instance of the Symfony SMTP Transport driver.
     *
     * @param array $config
     *
     * @return \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport
     */
    protected function createSmtpTransport(array $config) {
        $factory = new EsmtpTransportFactory();

        $scheme = '';
        if (!empty($config['encryption']) && $config['encryption'] === 'tls') {
            // smtps = TLS implisit (465); di port lain STARTTLS dinegosiasikan sendiri oleh EsmtpTransport
            $scheme = (($config['port'] ?? 587) == 465) ? 'smtps' : 'smtp';
        }
        $transport = $factory->create(new Dsn(
            $scheme,
            $config['host'],
            $config['username'] ?? null,
            $config['password'] ?? null,
            $config['port'] ?? null,
            $config
        ));

        return $this->configureSmtpTransport($transport, $config);
    }

    /**
     * Configure the additional SMTP driver options.
     *
     * @param \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport $transport
     * @param array                                                   $config
     *
     * @return \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport
     */
    protected function configureSmtpTransport(EsmtpTransport $transport, array $config) {
        $stream = $transport->getStream();

        if ($stream instanceof SocketStream) {
            if (isset($config['source_ip'])) {
                $stream->setSourceIp($config['source_ip']);
            }

            if (isset($config['timeout'])) {
                $stream->setTimeout($config['timeout']);
            }
        }

        return $transport;
    }

    /**
     * Create an instance of the Symfony Sendmail Transport driver.
     *
     * @param array $config
     *
     * @return \Symfony\Component\Mailer\Transport\SendmailTransport
     */
    protected function createSendmailTransport(array $config) {
        return new SendmailTransport(
            $config['path'] ?? CF::config('email.sendmail')
        );
    }

    /**
     * Create an instance of the Sendgrid Transport driver.
     *
     * @param array $config
     *
     * @return \CEmail_Transport_Sendgrid
     */
    protected function createSendgridTransport(array $config) {
        $config = array_merge(
            CF::config('vendor.sendgrid', []),
            $config
        );

        $config = carr::except($config, ['transport']);
        $apiKey = carr::get($config, 'key');
        $sendgrid = new CVendor_SendGrid($apiKey);

        return new CEmail_Transport_SendgridTransport(
            $sendgrid,
            $config['options'] ?? []
        );
    }

    /**
     * Create an instance of the Symfony Amazon SES Transport driver.
     *
     * @param array $config
     *
     * @return \CEmail_Transport_SesTransport
     */
    /**
     * @param array $config
     *
     * @return CEmail_Transport_BrevoTransport
     */
    protected function createBrevoTransport(array $config) {
        $key = carr::get($config, 'key') ?: CF::config('vendor.brevo.api_key');

        return new CEmail_Transport_BrevoTransport(CVendor_Brevo::transactionalEmail(['apiKey' => $key]));
    }

    /**
     * @param array $config
     *
     * @return CEmail_Transport_MailersendTransport
     */
    protected function createMailersendTransport(array $config) {
        $key = carr::get($config, 'key') ?: CF::config('vendor.mailersend.api_key');

        return new CEmail_Transport_MailersendTransport(new CVendor_MailerSend(['api_key' => $key]));
    }

    /**
     * @param array $config
     *
     * @return CEmail_Transport_KirimEmailTransport
     */
    protected function createKirimemailTransport(array $config) {
        $key = carr::get($config, 'key') ?: CF::config('vendor.kirimemail.api_key');

        return new CEmail_Transport_KirimEmailTransport($key, carr::get($config, 'domain'));
    }

    protected function createSesTransport(array $config) {
        $config = array_merge(
            CF::config('vendor.ses', []),
            ['version' => 'latest', 'service' => 'email'],
            $config
        );

        $config = carr::except($config, ['transport']);

        return new CEmail_Transport_SesTransport(
            new SesClient($this->addSesCredentials($config)),
            $config['options'] ?? []
        );
    }

    /**
     * Create an instance of the Symfony Amazon SES V2 Transport driver.
     *
     * @param array $config
     *
     * @return \CEMail_Transport_SesV2Transport
     */
    protected function createSesV2Transport(array $config) {
        $config = array_merge(
            CF::config('vendor.ses', []),
            ['version' => 'latest'],
            $config
        );

        $config = carr::except($config, ['transport']);

        return new CEmail_Transport_SesV2Transport(
            new SesV2Client($this->addSesCredentials($config)),
            $config['options'] ?? []
        );
    }

    /**
     * Add the SES credentials to the configuration array.
     *
     * @param array $config
     *
     * @return array
     */
    protected function addSesCredentials(array $config) {
        if (!empty($config['key']) && !empty($config['secret'])) {
            $config['credentials'] = carr::only($config, ['key', 'secret', 'token']);
        }

        return $config;
    }

    /**
     * Create an instance of the Symfony Mail Transport driver.
     *
     * @return \Symfony\Component\Mailer\Transport\SendmailTransport
     */
    protected function createMailTransport() {
        return new SendmailTransport();
    }

    /**
     * Create an instance of the Symfony Mailgun Transport driver.
     *
     * @param array $config
     *
     * @return \Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunApiTransport
     */
    protected function createMailgunTransport(array $config) {
        $factory = new MailgunTransportFactory();

        if (!isset($config['secret'])) {
            $config = CF::config('vendor.mailgun', []);
        }

        return $factory->create(new Dsn(
            'mailgun+api',
            $config['endpoint'] ?? 'default',
            $config['secret'],
            $config['domain']
        ));
    }

    /**
     * Create an instance of the Symfony Postmark Transport driver.
     *
     * @param array $config
     *
     * @return \Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkApiTransport
     */
    protected function createPostmarkTransport(array $config) {
        $factory = new PostmarkTransportFactory();

        $options = isset($config['message_stream_id'])
                    ? ['message_stream' => $config['message_stream_id']]
                    : [];

        return $factory->create(new Dsn(
            'postmark+api',
            'default',
            $config['token'] ?? CF::config('vendor.postmark.token'),
            null,
            null,
            $options
        ));
    }

    /**
     * Create an instance of the Symfony Failover Transport driver.
     *
     * @param array $config
     *
     * @return \Symfony\Component\Mailer\Transport\FailoverTransport
     */
    protected function createFailoverTransport(array $config) {
        $transports = [];

        foreach ($config['mailers'] as $name) {
            $config = $this->getConfig($name);

            if (is_null($config)) {
                throw new InvalidArgumentException("Mailer [{$name}] is not defined.");
            }

            // Now, we will check if the "driver" key exists and if it does we will set
            // the transport configuration parameter in order to offer compatibility
            // with any Laravel <= 6.x application style mail configuration files.
            $transports[] = CF::config('email.driver')
                ? $this->createSymfonyTransport(array_merge($config, ['transport' => $name]))
                : $this->createSymfonyTransport($config);
        }

        return new FailoverTransport($transports);
    }

    /**
     * Create an instance of the Log Transport driver.
     *
     * @param array $config
     *
     * @return \CEmail_Transport_LogTransport
     */
    protected function createLogTransport(array $config) {
        // $logger = CContainer::getInstance()->make(LoggerInterface::class);
        $logger = CLogger_Manager::instance();

        if ($logger instanceof CLogger_Manager) {
            $logger = $logger->channel(
                $config['channel'] ?? CF::config('email.log_channel')
            );
        }

        return new CEmail_Transport_LogTransport($logger);
    }

    /**
     * Create an instance of the Array Transport Driver.
     *
     * @return \CEmail_Transport_ArrayTransport
     */
    protected function createArrayTransport() {
        return new CEmail_Transport_ArrayTransport();
    }

    /**
     * Set a global address on the mailer by type.
     *
     * @param \CEmail_Mailer $mailer
     * @param array          $config
     * @param string         $type
     *
     * @return void
     */
    protected function setGlobalAddress($mailer, array $config, string $type) {
        $address = carr::get($config, $type, CF::config('email.' . $type));

        if (is_array($address) && isset($address['address'])) {
            $mailer->{'always' . cstr::studly($type)}($address['address'], $address['name']);
        }
    }

    /**
     * Get the mail connection configuration.
     *
     * @param string $name
     *
     * @return array
     */
    protected function getConfig(string $name) {
        // Here we will check if the "driver" key exists and if it does we will use
        // the entire mail configuration file as the "driver" config in order to
        // provide "BC" for any Laravel <= 6.x style mail configuration files.

        return CF::config('email.driver') ? CF::config('email') : CF::config("email.mailers.{$name}");
    }

    /**
     * Get the default mail driver name.
     *
     * @return string
     */
    public function getDefaultDriver() {
        // Here we will check if the "driver" key exists and if it does we will use
        // that as the default driver in order to provide support for old styles
        // of the Laravel mail configuration file for backwards compatibility.
        return CF::config('email.driver', CF::config('email.default'));
    }

    /**
     * Set the default mail driver name.
     *
     * @param string $name
     *
     * @return void
     */
    public function setDefaultDriver(string $name) {
        if (CConfig::repository()->get('email.driver')) {
            CConfig::repository()->set('email.driver', $name);
        }

        CConfig::repository()->set('email.default', $name);
    }

    /**
     * Disconnect the given mailer and remove from local cache.
     *
     * @param null|string $name
     *
     * @return void
     */
    public function purge($name = null) {
        $name = $name ?: $this->getDefaultDriver();

        unset($this->mailers[$name]);
    }

    /**
     * Register a custom transport creator Closure.
     *
     * @param string   $driver
     * @param \Closure $callback
     *
     * @return $this
     */
    public function extend($driver, Closure $callback) {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Forget all of the resolved mailer instances.
     *
     * @return $this
     */
    public function forgetMailers() {
        $this->mailers = [];

        return $this;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters) {
        return $this->mailer()->$method(...$parameters);
    }
}
