<?php

defined('SYSPATH') or die('No direct access allowed.');

class CServer_Storage_Info {
    /**
     * Array with disk devices.
     *
     * @see CServer_Device_Disk
     *
     * @var array
     */
    private $diskDevices = [];

    /**
     * Returns $diskDevices.
     *
     * @see CServer_Storage_Info::$diskDevices
     *
     * @return array
     */
    public function getDiskDevices() {
        return $this->diskDevices;
    }

    /**
     * Sets $_diskDevices.
     *
     * @param CServer_Device_Disk $diskDevices disk device
     *
     * @see CServer_Storage_Info::$diskDevices
     * @see CServer_Device_Disk
     *
     * @return void
     */
    public function setDiskDevices(CServer_Device_Disk $diskDevices) {
        array_push($this->diskDevices, $diskDevices);
    }
}
