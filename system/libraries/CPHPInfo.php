<?php

//@codingStandardsIgnoreStart
/**
 * @see CServer_PhpInfo
 * @deprecated 2.0
 */
class CPHPInfo {
    private static $_instance = null;
    private $_info = [];

    public function __construct() {
        $this->_info = CServer::phpInfo()->get();
    }

    public static function instance() {
        if (static::$_instance == null) {
            static::$_instance = new static();
        }
        return static::$_instance;
    }

    public function get_array() {
        return $this->_info;
    }

    public function system() {
        if (isset($this->_info['phpinfo']['System'])) {
            return $this->_info['phpinfo']['System'];
        }
        return false;
    }

    public function server_api() {
        if (isset($this->_info['phpinfo']['Server API'])) {
            return $this->_info['phpinfo']['Server API'];
        }
        return false;
    }

    public function loaded_configuration_file() {
        if (isset($this->_info['phpinfo']['Loaded Configuration File'])) {
            return $this->_info['phpinfo']['Loaded Configuration File'];
        }
        return false;
    }
}
//@codingStandardsIgnoreEnd
