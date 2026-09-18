<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since May 3, 2019, 1:30:18 PM
 */
abstract class CGit_ModelAbstract {
    protected $repository;

    public function getRepository() {
        return $this->repository;
    }

    public function setRepository($repository) {
        $this->repository = $repository;
        return $this;
    }
}
