<?php

defined('SYSPATH') or die('No direct access allowed.');

class CGit {
    /**
     * @param string $path
     *
     * @return CGit_Repository
     */
    public static function getRepository($path) {
        $client = new CGit_Client();

        return $client->getRepository($path);
    }
}
