<?php

class CReport_Jasper_Manager_DefaultSetting {
    protected $defaultLineSpacing;

    public function __construct() {
        $this->defaultLineSpacing = 1;
    }

    /**
     * @return float
     */
    public function getDefaultLineSpacing() {
        return $this->defaultLineSpacing;
    }
}
