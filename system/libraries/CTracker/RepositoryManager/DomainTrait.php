<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 3:28:33 PM
 */
trait CTracker_RepositoryManager_DomainTrait {
    /**
     * @var CTracker_Repository_Domain
     */
    protected $domainRepository;

    protected function bootDomainTrait() {
        $this->domainRepository = new CTracker_Repository_Domain();
    }

    public function getDomainId($domain) {
        return $this->domainRepository->findOrCreate(
            ['name' => $domain],
            ['name']
        );
    }
}
