<?php

defined('SYSPATH') or die('No direct access allowed.');

class CRemote_SSH_Config {
    const AUTH_TYPE_PROMPT = 'prompt';

    const AUTH_TYPE_PUBKEY = 'pubkey';

    const AUTH_TYPE_AGENT = 'agent';

    /**
     * @var string
     */
    protected $host;

    /**
     * @var int
     */
    protected $port = 22;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var null|string
     */
    protected $password;

    /**
     * @var null|string
     */
    protected $privateKey;

    /**
     * @var null|string
     */
    protected $publicKey;

    /**
     * @var null|string
     */
    protected $keyPath;

    /**
     * @var bool
     */
    protected $useAgent = false;

    /**
     * @var int
     */
    protected $timeout = 10;

    /**
     * @var null|string
     */
    protected $authenticationType;

    /**
     * @var null|string
     */
    protected $ipAddress;

    /**
     * Bastion/jump host this connection tunnels through, or null to connect directly.
     *
     * @var null|CRemote_SSH_Config
     */
    protected $proxyJump;

    /**
     * @param array $config
     */
    public function __construct(array $config = []) {
        $this->host = carr::get($config, 'host', '');
        $this->ipAddress = carr::get($config, 'ip_address');
        $this->port = (int) carr::get($config, 'port', 22);
        $this->username = carr::get($config, 'username', '');
        $this->password = carr::get($config, 'password');
        $this->privateKey = carr::get($config, 'keytext');
        $this->publicKey = carr::get($config, 'public_key');
        $this->keyPath = carr::get($config, 'key');
        $this->useAgent = carr::get($config, 'agent', false) === true;
        $this->timeout = (int) carr::get($config, 'timeout', 10);
        $this->authenticationType = carr::get($config, 'authentication_type');

        $proxyJump = carr::get($config, 'proxy_jump');
        if ($proxyJump !== null) {
            $this->setProxyJump($proxyJump);
        }
    }

    /**
     * @param string $host
     *
     * @return $this
     */
    public function setHost($host) {
        $this->host = $host;

        return $this;
    }

    /**
     * @return string
     */
    public function getHost() {
        return $this->host;
    }

    /**
     * @param null|string $ipAddress
     *
     * @return $this
     */
    public function setIpAddress($ipAddress) {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getIpAddress() {
        return $this->ipAddress;
    }

    /**
     * @param array|CRemote_SSH_Config $proxyJump
     *
     * @return $this
     */
    public function setProxyJump($proxyJump) {
        $this->proxyJump = $proxyJump instanceof self ? $proxyJump : new self($proxyJump);

        return $this;
    }

    /**
     * @return null|CRemote_SSH_Config
     */
    public function getProxyJump() {
        return $this->proxyJump;
    }

    /**
     * @return bool
     */
    public function hasProxyJump() {
        return $this->proxyJump !== null;
    }

    /**
     * @return string
     */
    public function getConnectionHost() {
        return $this->ipAddress ?: $this->host;
    }

    /**
     * @param int $port
     *
     * @return $this
     */
    public function setPort($port) {
        $this->port = (int) $port;

        return $this;
    }

    /**
     * @return int
     */
    public function getPort() {
        return $this->port;
    }

    /**
     * @param string $username
     *
     * @return $this
     */
    public function setUsername($username) {
        $this->username = $username;

        return $this;
    }

    /**
     * @return string
     */
    public function getUsername() {
        return $this->username;
    }

    /**
     * @param null|string $password
     *
     * @return $this
     */
    public function setPassword($password) {
        $this->password = $password;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getPassword() {
        return $this->password;
    }

    /**
     * @param null|string $privateKey
     *
     * @return $this
     */
    public function setPrivateKey($privateKey) {
        $this->privateKey = $privateKey;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getPrivateKey() {
        return $this->privateKey;
    }

    /**
     * @param null|string $publicKey
     *
     * @return $this
     */
    public function setPublicKey($publicKey) {
        $this->publicKey = $publicKey;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getPublicKey() {
        return $this->publicKey;
    }

    /**
     * @param null|string $keyPath
     *
     * @return $this
     */
    public function setKeyPath($keyPath) {
        $this->keyPath = $keyPath;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getKeyPath() {
        return $this->keyPath;
    }

    /**
     * @param bool $useAgent
     *
     * @return $this
     */
    public function setUseAgent($useAgent) {
        $this->useAgent = (bool) $useAgent;

        return $this;
    }

    /**
     * @return bool
     */
    public function getUseAgent() {
        return $this->useAgent;
    }

    /**
     * @param int $timeout
     *
     * @return $this
     */
    public function setTimeout($timeout) {
        $this->timeout = (int) $timeout;

        return $this;
    }

    /**
     * @return int
     */
    public function getTimeout() {
        return $this->timeout;
    }

    /**
     * @param null|string $authenticationType
     *
     * @return $this
     */
    public function setAuthenticationType($authenticationType) {
        $this->authenticationType = $authenticationType;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getAuthenticationType() {
        return $this->authenticationType;
    }

    /**
     * @return bool
     */
    public function hasPrivateKey() {
        return ($this->privateKey !== null && trim($this->privateKey) !== '')
            || ($this->keyPath !== null && trim($this->keyPath) !== '');
    }

    /**
     * @return bool
     */
    public function hasPassword() {
        return $this->password !== null && trim($this->password) !== '';
    }

    /**
     * @return string
     */
    public function derivePublicKey() {
        if ($this->privateKey === null || trim($this->privateKey) === '') {
            throw new \InvalidArgumentException('Private key is required to derive public key');
        }

        $loadedKey = \phpseclib3\Crypt\PublicKeyLoader::loadPrivateKey(trim($this->privateKey));

        return $loadedKey->getPublicKey()->toString('OpenSSH');
    }

    /**
     * @return array
     */
    public static function validAuthenticationTypes() {
        return [self::AUTH_TYPE_PROMPT, self::AUTH_TYPE_PUBKEY, self::AUTH_TYPE_AGENT];
    }

    /**
     * @return bool
     */
    public function isValid() {
        if (strlen($this->host) == 0) {
            return false;
        }
        if (strlen($this->username) == 0) {
            return false;
        }
        if ($this->authenticationType !== null && !in_array($this->authenticationType, self::validAuthenticationTypes())) {
            return false;
        }
        if ($this->authenticationType === self::AUTH_TYPE_PUBKEY && !$this->hasPrivateKey()) {
            return false;
        }
        if ($this->authenticationType === self::AUTH_TYPE_PROMPT && !$this->hasPassword()) {
            return false;
        }
        if ($this->authenticationType === null && !$this->useAgent && !$this->hasPrivateKey() && !$this->hasPassword()) {
            return false;
        }

        return true;
    }

    /**
     * @throws \InvalidArgumentException
     *
     * @return $this
     */
    public function validate() {
        if (strlen($this->host) == 0) {
            throw new \InvalidArgumentException('Host is required');
        }
        if (strlen($this->username) == 0) {
            throw new \InvalidArgumentException('Username is required');
        }
        if ($this->authenticationType !== null && !in_array($this->authenticationType, self::validAuthenticationTypes())) {
            throw new \InvalidArgumentException('Invalid authentication type: ' . $this->authenticationType . '. Valid types: ' . implode(', ', self::validAuthenticationTypes()));
        }
        if ($this->authenticationType === self::AUTH_TYPE_PUBKEY && !$this->hasPrivateKey()) {
            throw new \InvalidArgumentException('Private key is required for pubkey authentication');
        }
        if ($this->authenticationType === self::AUTH_TYPE_PROMPT && !$this->hasPassword()) {
            throw new \InvalidArgumentException('Password is required for prompt authentication');
        }
        if ($this->authenticationType === null && !$this->useAgent && !$this->hasPrivateKey() && !$this->hasPassword()) {
            throw new \InvalidArgumentException('Password or key is required');
        }

        return $this;
    }

    /**
     * @return array
     */
    public function toArray() {
        return [
            'host' => $this->host,
            'ip_address' => $this->ipAddress,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'keytext' => $this->privateKey,
            'public_key' => $this->publicKey,
            'key' => $this->keyPath,
            'agent' => $this->useAgent,
            'timeout' => $this->timeout,
            'authentication_type' => $this->authenticationType,
            'proxy_jump' => $this->proxyJump !== null ? $this->proxyJump->toArray() : null,
        ];
    }

    /**
     * @return array
     */
    public function toAuthArray() {
        if ($this->useAgent) {
            return ['agent' => true];
        }
        if ($this->keyPath !== null && trim($this->keyPath) !== '') {
            return ['key' => $this->keyPath, 'keyphrase' => ''];
        }
        if ($this->privateKey !== null && trim($this->privateKey) !== '') {
            return ['keytext' => $this->privateKey];
        }
        if ($this->password !== null) {
            return ['password' => $this->password];
        }

        throw new \InvalidArgumentException('Password or key is required');
    }
}
