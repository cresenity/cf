<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since May 3, 2019, 1:42:04 PM
 */
class CGit_Model_Line extends CGit_ModelAbstract {
    protected $line;

    protected $type;

    public function __construct($data) {
        if (!empty($data)) {
            if ('@' == $data[0]) {
                $this->setType('chunk');
            }
            if ('-' == $data[0]) {
                $this->setType('old');
            }
            if ('+' == $data[0]) {
                $this->setType('new');
            }
        }
        $this->setLine($data);
    }

    public function getLine() {
        return $this->line;
    }

    public function setLine($line) {
        $this->line = $line;
        return $this;
    }

    public function getType() {
        return $this->type;
    }

    public function setType($type) {
        $this->type = $type;
        return $this;
    }
}
