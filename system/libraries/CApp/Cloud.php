<?php

defined('SYSPATH') or die('No direct access allowed.');

class CApp_Cloud {
    /**
     * @var CApp_Cloud_AdapterInterface
     */
    protected $adapter;

    /**
     * @var CApp_Cloud
     */
    protected static $instance;

    /**
     * @return CApp_Cloud
     */
    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new CApp_Cloud();
        }

        return self::$instance;
    }

    /**
     * @param CApp_Cloud_AdapterInterface $adapter
     */
    private function __construct(CApp_Cloud_AdapterInterface $adapter = null) {
        if ($adapter == null) {
            $adapter = new CApp_Cloud_Adapter_GuzzleAdapter();
        }
        $this->adapter = $adapter;
    }

    public function api($apiName) {
        $api = new CApp_Cloud_Api($this->adapter);

        return $api->execute($apiName);
    }
}
