<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Apr 14, 2019, 6:30:21 PM
 */

// @codingStandardsIgnoreStart
trait CTrait_Compat_Excel {
    /**
     * @deprecated 1.2
     */
    public function garbage_collect() {
        return $this->garbageCollect();
    }

    /**
     * @deprecated 1.2
     *
     * @param string $creator
     */
    public function set_creator($creator) {
        return $this->setCreator($creator);
    }

    /**
     * @deprecated 1.2
     *
     * @param string $title
     */
    public function set_title($title) {
        return $this->setTitle($title);
    }

    /**
     * @deprecated 1.2
     *
     * @param string $subject
     */
    public function set_subject($subject) {
        return $this->setSubject($subject);
    }

    /**
     * @deprecated 1.2
     *
     * @param string $description
     */
    public function set_description($description) {
        return $this->setDescription($description);
    }

    /**
     * @deprecated 1.2
     */
    public function get_highest_row() {
        return $this->getHighestRow();
    }

    /**
     * @deprecated 1.2
     */
    public function get_active_sheet_name() {
        return $this->getActiveSheetName();
    }

    /**
     * @deprecated 1.2
     *
     * @param mixed $cell
     * @param mixed $value
     */
    public function write_cell($cell, $value) {
        return $this->writeCell($cell, $value);
    }

    /**
     * @deprecated 1.2
     *
     * @param mixed $cell
     */
    public function read_cell($cell) {
        return $this->readCell($cell);
    }

    /**
     * @deprecated 1.2
     *
     * @param mixed $column
     * @param mixed $row
     */
    public function read_by_index($column, $row) {
        return $this->readByIndex($column, $row);
    }

    /**
     * @deprecated 1.2
     *
     * @param mixed $col
     * @param mixed $row
     * @param mixed $source
     *
     * @return $this
     */
    public function set_list_validation_by_index($col, $row, $source) {
        return $this->setListValidationByIndex($col, $row, $source);
    }

    /**
     * @deprecated 1.2
     *
     * @param mixed $index
     * @param mixed $width
     */
    public function set_column_width($index, $width) {
        return $this->setColumnWidth($index, $width);
    }
}
