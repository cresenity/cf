<?php

/**
 * Description of EncrypterInterface.
 */
interface CCrypt_EncrypterInterface {
    /**
     * Encrypt the given value.
     *
     * @param mixed $value
     * @param bool  $serialize
     *
     * @throws \CCrypt_Exception_EncryptException
     *
     * @return string
     */
    public function encrypt($value, $serialize = true);

    /**
     * Decrypt the given value.
     *
     * @param string $payload
     * @param bool   $unserialize
     *
     * @throws \CCrypt_Exception_DecryptException
     *
     * @return mixed
     */
    public function decrypt($payload, $unserialize = true);
}
