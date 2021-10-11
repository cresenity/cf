<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * This class is used to construct a Category object for the /mail/send API call
 *
 * @package SendGrid\Mail
 */
class CVendor_SendGrid_Mail_Category implements \JsonSerializable {
    /**
     * @var string A category name for an email message. Each category name may not exceed 255 characters
     */
    private $category;

    /**
     * Optional constructor
     *
     * @param string|null $category A category name for an email message.
     *                              Each category name may not exceed 255
     *                              characters
     */
    public function __construct($category = null) {
        if (isset($category)) {
            $this->setCategory($category);
        }
    }

    /**
     * Add a category to a Category object
     *
     * @param string $category A category name for an email message.
     *                         Each category name may not exceed 255
     *                         characters
     *
     * @throws CVendor_SendGrid_Exception_TypeException
     */
    public function setCategory($category) {
        if (!is_string($category)) {
            throw new CVendor_SendGrid_Exception_TypeException('$category must be of type string.');
        }
        $this->category = $category;
    }

    /**
     * Retrieve a category from a Category object
     *
     * @return string
     */
    public function getCategory() {
        return $this->category;
    }

    /**
     * Return an array representing a Category object for the Twilio SendGrid API
     *
     * @return string
     */
    public function jsonSerialize() {
        return $this->getCategory();
    }
}
