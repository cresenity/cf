<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @deprecated 1.8
 */
abstract class CDetector_Crawler_AbstractProvider {
    /**
     * The data set.
     *
     * @var array
     */
    protected $data;

    /**
     * Return the data set.
     *
     * @return array
     */
    public function getAll() {
        return $this->data;
    }
}
