<?php

if (!is_callable('random_bytes')) {
    require_once DOCROOT . 'system/vendor/random_compat/random.php';
}
/**
 * Description of Encrypter.
 */
class CCrypt_Encrypter implements CCrypt_EncrypterInterface {
    /**
     * The encryption key.
     *
     * @var string
     */
    protected $key;

    /**
     * The algorithm used for encryption.
     *
     * @var string
     */
    protected $cipher;

    /**
     * The supported cipher algorithms and their properties.
     *
     * @var array
     */
    private static $supportedCiphers = [
        'aes-128-cbc' => ['size' => 16, 'aead' => false],
        'aes-256-cbc' => ['size' => 32, 'aead' => false],
        'aes-128-gcm' => ['size' => 16, 'aead' => true],
        'aes-256-gcm' => ['size' => 32, 'aead' => true],
    ];

    /**
     * Create a new encrypter instance.
     *
     * @param string $key
     * @param string $cipher
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    public function __construct($key, $cipher = 'aes-128-cbc') {
        $key = (string) $key;
        if (!static::supported($key, $cipher)) {
            $ciphers = implode(', ', array_keys(self::$supportedCiphers));

            throw new RuntimeException("Unsupported cipher or incorrect key length. Supported ciphers are: {$ciphers}.");
        }
        $this->key = $key;
        $this->cipher = $cipher;
    }

    /**
     * Determine if the given key and cipher combination is valid.
     *
     * @param string $key
     * @param string $cipher
     *
     * @return bool
     */
    public static function supported($key, $cipher) {
        if (!isset(self::$supportedCiphers[strtolower($cipher)])) {
            return false;
        }

        return mb_strlen($key, '8bit') === self::$supportedCiphers[strtolower($cipher)]['size'];
    }

    /**
     * Create a new encryption key for the given cipher.
     *
     * @param string $cipher
     *
     * @return string
     */
    public static function generateKey($cipher) {
        $size = 32;
        if (isset(self::$supportedCiphers[strtolower($cipher)], self::$supportedCiphers[strtolower($cipher)]['size'])) {
            $size = self::$supportedCiphers[strtolower($cipher)]['size'];
        }

        return random_bytes(self::$supportedCiphers[strtolower($cipher)]['size'] ?? 32);
    }

    /**
     * Encrypt the given value.
     *
     * @param mixed $value
     * @param bool  $serialize
     *
     * @throws CCrypt_Exception_EncryptException
     *
     * @return string
     */
    public function encrypt($value, $serialize = true) {
        $iv = random_bytes(openssl_cipher_iv_length(strtolower($this->cipher)));

        // First we will encrypt the value using OpenSSL. After this is encrypted we
        // will proceed to calculating a MAC for the encrypted value so that this
        // value can be verified later as not having been changed by the users.
        $value = \openssl_encrypt(
            $serialize ? serialize($value) : $value,
            strtolower($this->cipher),
            $this->key,
            0,
            $iv,
            $tag
        );

        if ($value === false) {
            throw new CCrypt_Exception_EncryptException('Could not encrypt the data.');
        }
        $iv = base64_encode($iv);
        $tag = base64_encode($tag ?: '');

        $mac = self::$supportedCiphers[strtolower($this->cipher)]['aead']
            ? '' // For AEAD-algorithms, the tag / MAC is returned by openssl_encrypt...
            : $this->hash($iv, $value);

        $json = json_encode(compact('iv', 'value', 'mac', 'tag'), JSON_UNESCAPED_SLASHES);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new CCrypt_Exception_EncryptException('Could not encrypt the data.');
        }

        return base64_encode($json);
    }

    /**
     * Encrypt a string without serialization.
     *
     * @param string $value
     *
     * @throws CCrypt_Exception_EncryptException
     *
     * @return string
     */
    public function encryptString($value) {
        return $this->encrypt($value, false);
    }

    /**
     * Decrypt the given value.
     *
     * @param string $payload
     * @param bool   $unserialize
     *
     * @throws CCrypt_Exception_DecryptException
     *
     * @return mixed
     */
    public function decrypt($payload, $unserialize = true) {
        $payload = $this->getJsonPayload($payload);

        $iv = base64_decode($payload['iv']);
        $this->ensureTagIsValid(
            $tag = empty($payload['tag']) ? null : base64_decode($payload['tag'])
        );
        // Here we will decrypt the value. If we are able to successfully decrypt it
        // we will then unserialize it and return it out to the caller. If we are
        // unable to decrypt this value we will throw out an exception message.
        $decrypted = \openssl_decrypt(
            $payload['value'],
            $this->cipher,
            $this->key,
            0,
            $iv,
            $tag ?: ''
        );

        if ($decrypted === false) {
            throw new CCrypt_Exception_DecryptException('Could not decrypt the data.');
        }

        return $unserialize ? unserialize($decrypted) : $decrypted;
    }

    /**
     * Decrypt the given string without unserialization.
     *
     * @param string $payload
     *
     * @throws CCrypt_Exception_DecryptException
     *
     * @return string
     */
    public function decryptString($payload) {
        return $this->decrypt($payload, false);
    }

    /**
     * Create a MAC for the given value.
     *
     * @param string $iv
     * @param mixed  $value
     *
     * @return string
     */
    protected function hash($iv, $value) {
        return hash_hmac('sha256', $iv . $value, $this->key);
    }

    /**
     * Get the JSON array from the given payload.
     *
     * @param string $payload
     *
     * @throws CCrypt_Exception_DecryptException
     *
     * @return array
     */
    protected function getJsonPayload($payload) {
        $payload = json_decode(base64_decode($payload), true);

        // If the payload is not valid JSON or does not have the proper keys set we will
        // assume it is invalid and bail out of the routine since we will not be able
        // to decrypt the given value. We'll also check the MAC for this encryption.
        if (!$this->validPayload($payload)) {
            throw new CCrypt_Exception_DecryptException('The payload is invalid.');
        }

        if (!self::$supportedCiphers[strtolower($this->cipher)]['aead'] && !$this->validMac($payload)) {
            throw new CCrypt_Exception_DecryptException('The MAC is invalid.');
        }

        return $payload;
    }

    /**
     * Verify that the encryption payload is valid.
     *
     * @param mixed $payload
     *
     * @return bool
     */
    protected function validPayload($payload) {
        if (!is_array($payload)) {
            return false;
        }

        foreach (['iv', 'value', 'mac'] as $item) {
            if (!isset($payload[$item]) || !is_string($payload[$item])) {
                return false;
            }
        }

        if (isset($payload['tag']) && !is_string($payload['tag'])) {
            return false;
        }

        return strlen(base64_decode($payload['iv'], true)) === openssl_cipher_iv_length(strtolower($this->cipher));
    }

    /**
     * Determine if the MAC for the given payload is valid.
     *
     * @param array $payload
     *
     * @return bool
     */
    protected function validMac(array $payload) {
        return hash_equals(
            $this->hash($payload['iv'], $payload['value']),
            $payload['mac']
        );
    }

    /**
     * Ensure the given tag is a valid tag given the selected cipher.
     *
     * @param string $tag
     *
     * @return void
     */
    protected function ensureTagIsValid($tag) {
        if (self::$supportedCiphers[strtolower($this->cipher)]['aead'] && strlen($tag) !== 16) {
            throw new CCrypt_Exception_DecryptException('Could not decrypt the data.');
        }

        if (!self::$supportedCiphers[strtolower($this->cipher)]['aead'] && is_string($tag)) {
            throw new CCrypt_Exception_DecryptException('Unable to use tag because the cipher algorithm does not support AEAD.');
        }
    }

    /**
     * Get the encryption key.
     *
     * @return string
     */
    public function getKey() {
        return $this->key;
    }
}
