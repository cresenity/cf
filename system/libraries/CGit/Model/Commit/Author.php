<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @deprecated 1.8
 */
class CGit_Model_Commit_Author extends CGit_ModelAbstract {
    protected $name;

    protected $email;

    public function __construct($name, $email) {
        $this->setName($name);
        $this->setEmail($email);
    }

    public function getName() {
        return $this->name;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function getEmail() {
        return $this->email;
    }

    public function setEmail($email) {
        $this->email = $email;
    }
}
