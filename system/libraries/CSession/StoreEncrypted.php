<?php

/**
 * Description of StoreEncrypted.
 */
class CSession_StoreEncrypted extends CSession_Store {
    /**
     * The encrypter instance.
     *
     * @var CCrypt_Encrypter
     */
    protected $encrypter;

    /**
     * Create a new session instance.
     *
     * @param string                    $name
     * @param \SessionHandlerInterface  $handler
     * @param CCrypt_EncrypterInterface $encrypter
     * @param null|string               $id
     *
     * @return void
     */
    public function __construct($name, SessionHandlerInterface $handler, CCrypt_EncrypterInterface $encrypter, $id = null) {
        $this->encrypter = $encrypter;

        parent::__construct($name, $handler, $id);
    }

    /**
     * Prepare the raw string data from the session for unserialization.
     *
     * @param string $data
     *
     * @return string
     */
    protected function prepareForUnserialize($data) {
        try {
            return $this->encrypter->decrypt($data);
        } catch (CCrypt_Exception_DecryptException $e) {
            return serialize([]);
        }
    }

    /**
     * Prepare the serialized session data for storage.
     *
     * @param string $data
     *
     * @return string
     */
    protected function prepareForStorage($data) {
        return $this->encrypter->encrypt($data);
    }

    /**
     * Get the encrypter instance.
     *
     * @return CCrypt_EncrypterInterface
     */
    public function getEncrypter() {
        return $this->encrypter;
    }
}
