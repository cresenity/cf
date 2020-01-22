<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * This class is used to construct a Section object for the /mail/send API call
 *
 * An object of key/value pairs that define block sections of code to be used
 * as substitutions
 *
 * @package SendGrid\Mail
 */
class CVendor_SendGrid_Mail_Section implements \JsonSerializable
{
    /** @var $key string Section key */
    private $key;
    /** @var $value string Section value */
    private $value;

    /**
     * Optional constructor
     *
     * @param string|null $key Section key
     * @param string|null $value Section value
     */
    public function __construct($key = null, $value = null)
    {
        if (isset($key)) {
            $this->setKey($key);
        }
        if (isset($value)) {
            $this->setValue($value);
        }
    }

    /**
     * Add the key on a Section object
     *
     * @param string $key Section key
     * 
     * @throws CVendor_SendGrid_Mail_TypeException
     */ 
    public function setKey($key)
    {
        if (!is_string($key)) {
            throw new CVendor_SendGrid_Mail_TypeException('$key must be of type string.');
        }
        $this->key = $key;
    }

    /**
     * Retrieve the key from a Section object
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Add the value on a Section object
     *
     * @param string $value Section value
     * 
     * @throws CVendor_SendGrid_Mail_TypeException
     */ 
    public function setValue($value)
    {
        if (!is_string($value)) {
            throw new CVendor_SendGrid_Mail_TypeException('$value must be of type string.');
        }
        $this->value = $value;
    }

    /**
     * Retrieve the value from a Section object
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Return an array representing a Section object for the Twilio SendGrid API
     *
     * @return null|array
     */
    public function jsonSerialize()
    {
        return array_filter(
            [
                'key' => $this->getKey(),
                'value' => $this->getValue()
            ],
            function ($value) {
                return $value !== null;
            }
        ) ?: null;
    }
}