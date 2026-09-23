<?php

use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Http\Adapter\Guzzle6\Client as GuzzleAdapter;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use Http\Client\Common\HttpMethodsClient as HttpClient;

/**
 * @property-read CVendor_OneSignal_Apps          $apps          Applications API service
 * @property-read CVendor_OneSignal_Devices       $devices       Devices API service
 * @property-read CVendor_OneSignal_Notifications $notifications Notifications API service
 */
class CVendor_OneSignal {
    const API_URL = 'https://onesignal.com/api/v1';

    /**
     * @var CVendor_OneSignal_Config
     */
    private $config;

    /**
     * @var ClientInterface
     */
    private $httpClient;

    /**
     * @var RequestFactoryInterface
     */
    private $requestFactory;

    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * @var CVendor_OneSignal_Resolver_ResolverFactory
     */
    private $resolverFactory;

    /**
     * @var array
     */
    private $services = [];

    /**
     * Constructor.
     *
     * @param CVendor_OneSignal_Config $config
     * @param Client                   $client
     */
    public function __construct(?CVendor_OneSignal_Config $config = null, ?ClientInterface $httpClient = null, ?RequestFactoryInterface $requestFactory = null, ?StreamFactoryInterface $streamFactory = null) {
        if ($httpClient == null) {
            $httpClient = new GuzzleClient();
        }

        if ($requestFactory == null || $streamFactory == null) {
            $factory = new HttpFactory();
            if ($requestFactory == null) {
                $requestFactory = $factory;
            }
            if ($streamFactory == null) {
                $streamFactory = $factory;
            }
        }
        $this->config = ($config ?: new CVendor_OneSignal_Config());
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;

        $this->httpClient = $httpClient;

        $this->resolverFactory = new CVendor_OneSignal_Resolver_ResolverFactory($this->config);
    }

    /**
     * @return RequestFactoryInterface
     */
    public function getRequestFactory() {
        return $this->requestFactory;
    }

    /**
     * @return StreamFactoryInterface
     */
    public function getStreamFactory() {
        return $this->streamFactory;
    }

    /**
     * Set config.
     *
     * @param Config $config
     */
    public function setConfig(CVendor_OneSignal_Config $config) {
        $this->config = $config;
    }

    /**
     * Get config.
     *
     * @return Config
     */
    public function getConfig() {
        return $this->config;
    }

    /**
     * Set client.
     *
     * @param ClientInterface $client
     */
    public function setClient(ClientInterface $client) {
        $this->client = $client;
    }

    /**
     * Get client.
     *
     * @return null|ClientInterface
     */
    public function getClient() {
        return $this->client;
    }

    public function sendRequest(RequestInterface $request): array {
        $response = $this->httpClient->sendRequest($request);

        $contentType = $response->getHeader('Content-Type')[0] ?? 'application/json';

        if (!preg_match('/\bjson\b/i', $contentType)) {
            throw new CVendor_OneSignal_Exception_JsonException("Response content-type is '{$contentType}' while a JSON-compatible one was expected.");
        }

        $content = $response->getBody()->__toString();

        try {
            $content = json_decode($content, true, 512, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new CVendor_OneSignal_Exception_JsonException($e->getMessage(), $e->getCode(), $e);
        }

        if (!is_array($content)) {
            throw new CVendor_OneSignal_Exception_JsonException(sprintf('JSON content was expected to decode to an array, %s returned.', gettype($content)));
        }

        return $content;
    }

    protected function api($name) {
        if (in_array($name, ['apps', 'devices', 'notifications'], true)) {
            if (isset($this->services[$name])) {
                return $this->services[$name];
            }
            $serviceName = 'CVendor_OneSignal_' . ucfirst($name);
            $this->services[$name] = new $serviceName($this, $this->resolverFactory);

            return $this->services[$name];
        }
        $trace = debug_backtrace();

        throw new CVendor_OneSignal_Exception_OneSignalException(sprintf('Undefined property via __get(): %s in %s on line %u', $name, $trace[0]['file'], $trace[0]['line']));
    }

    /**
     * Undocumented function.
     *
     * @return CVendor_OneSignal_Apps
     */
    public function apps() {
        return $this->api('apps');
    }

    /**
     * Undocumented function.
     *
     * @return CVendor_OneSignal_Devices
     */
    public function devices() {
        return $this->api('devices');
    }

    /**
     * Undocumented function.
     *
     * @return CVendor_OneSignal_Notifications
     */
    public function notifications() {
        return $this->api('notifications');
    }

    /**
     * Backs the `@property-read` services declared on this class.
     *
     * @param string $name
     *
     * @return CVendor_OneSignal_Apps|CVendor_OneSignal_Devices|CVendor_OneSignal_Notifications
     */
    public function __get($name) {
        return $this->api($name);
    }
}
