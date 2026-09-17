<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since May 3, 2019, 1:33:20 PM
 */
class CGit_Model_Blob extends CGit_Model_GitObject {
    protected $mode;

    protected $name;

    protected $size;

    public function __construct($hash, CGit_Repository $repository) {
        $this->setHash($hash);
        $this->setRepository($repository);
    }

    public function output() {
        $data = $this->getRepository()->getClient()->run($this->getRepository(), 'show ' . $this->getHash());
        return $data;
    }

    public function getMode() {
        return $this->mode;
    }

    public function setMode($mode) {
        $this->mode = $mode;
        return $this;
    }

    public function getName() {
        return $this->name;
    }

    public function setName($name) {
        $this->name = $name;
        return $this;
    }

    public function getSize() {
        return $this->size;
    }

    public function setSize($size) {
        $this->size = $size;
        return $this;
    }

    public function isBlob() {
        return true;
    }
}
