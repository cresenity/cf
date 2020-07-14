<?php

defined('SYSPATH') OR die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @since Jun 23, 2019, 4:09:44 PM
 * @license Ittron Global Teknologi <ittron.co.id>
 */
trait CTracker_RepositoryManager_LanguageTrait {

    /**
     *
     * @var CTracker_Repository_Language
     */
    protected $languageRepository;

    protected function bootLanguageTrait() {
        $this->languageRepository = new CTracker_Repository_Language();
    }

    public function findOrCreateLanguage($data) {

        return $this->languageRepository->findOrCreate($data, ['preference', 'language_range']);
    }

    public function getCurrentLanguage() {
        return CTracker::populator()->get('language');
    }

}
