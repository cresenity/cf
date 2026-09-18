<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 3:26:10 PM
 */
trait CTracker_RepositoryManager_CookieTrait {
    /**
     * @var CTracker_Repository_Cookie
     */
    protected $cookieRepository;

    protected function bootCookieTrait() {
        $this->cookieRepository = new CTracker_Repository_Cookie();
    }

    public function getCookieId() {
        return $this->cookieRepository->getId();
    }
}
