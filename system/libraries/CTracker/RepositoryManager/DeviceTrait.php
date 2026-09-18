<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 3:37:09 AM
 */
trait CTracker_RepositoryManager_DeviceTrait {
    /**
     * @var CTracker_Repository_Device
     */
    protected $deviceRepository;

    protected function bootDeviceTrait() {
        $this->deviceRepository = new CTracker_Repository_Device();
    }

    public function findOrCreateDevice($data) {
        return $this->deviceRepository->findOrCreate($data, ['kind', 'model', 'platform', 'platform_version']);
    }

    public function getCurrentDeviceProperties() {
        $properties = CTracker::populator()->get('device');
        return $properties;
    }
}
