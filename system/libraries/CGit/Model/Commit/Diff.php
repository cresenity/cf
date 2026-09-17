<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since May 3, 2019, 1:44:25 PM
 */
class CGit_Model_Commit_Diff extends CGit_ModelAbstract {
    protected $lines;

    protected $index;

    protected $old;

    protected $new;

    protected $file;

    public function addLine($line, $oldNo, $newNo) {
        $this->lines[] = new CGit_Model_Commit_DiffLine($line, $oldNo, $newNo);
    }

    public function getLines() {
        return $this->lines;
    }

    public function setIndex($index) {
        $this->index = $index;
    }

    public function getIndex() {
        return $this->index;
    }

    public function setOld($old) {
        $this->old = $old;
    }

    public function getOld() {
        return $this->old;
    }

    public function setNew($new) {
        $this->new = $new;
    }

    public function getNew() {
        return $this->new;
    }

    public function setFile($file) {
        $this->file = $file;
    }

    public function getFile() {
        return $this->file;
    }
}
