<?php

defined('SYSPATH') OR die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @since May 3, 2019, 1:29:49 PM
 * @license Ittron Global Teknologi <ittron.co.id>
 */
class CGit_Model_GitObject extends CGit_ModelAbstract {

    protected $hash;

    public function isBlob() {
        return false;
    }

    public function isTag() {
        return false;
    }

    public function isCommit() {
        return false;
    }

    public function isTree() {
        return false;
    }

    public function getHash() {
        return $this->hash;
    }

    public function setHash($hash) {
        $this->hash = $hash;
        return $this;
    }

}
