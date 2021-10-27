<?php

class CServer_Dns_Result_AResult extends CServer_Dns_Result {
    /**
     * @var string
     */
    private $ipv4;

    public function __construct($ip) {
        parent::__construct();
        $this->setIpv4($ip);
    }

    /**
     * @param string $ip
     */
    public function setIpv4($ip) {
        $this->ipv4 = $ip;
    }

    /**
     * @return string
     */
    public function getIpv4() {
        return $this->ipv4;
    }
}
