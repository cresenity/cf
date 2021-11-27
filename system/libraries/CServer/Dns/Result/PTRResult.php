<?php

class CServer_Dns_Result_PTRResult extends CServer_Dns_Result {
    private $data;

    public function __construct($data) {
        parent::__construct();
        $this->setData($data);
    }

    public function setData($data) {
        $this->data = $data;
    }

    public function getData() {
        return $this->data;
    }
}
