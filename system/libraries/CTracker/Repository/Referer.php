<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 3:15:50 PM
 */
class CTracker_Repository_Referer extends CTracker_AbstractRepository {
    /**
     * @var CTracker_Parser_RefererParser
     */
    private $refererParser;

    /**
     * @var string
     */
    private $currentUrl;

    /**
     * @var CTracker_Model_RefererSearchTerm
     */
    private $searchTermModel;

    public function __construct() {
        $this->className = CTracker::config()->get('refererModel', CTracker_Model_Referer::class);
        $this->createModel();
        $this->refererParser = new CTracker_Parser_RefererParser();
        $this->currentUrl = curl::current(true);
        $searchTermModelClass = CTracker::config()->get('refererSearchTermModel', CTracker_Model_RefererSearchTerm::class);
        $this->searchTermModel = new $searchTermModelClass();
        parent::__construct();
    }

    /**
     * @param $refererUrl
     * @param $host
     * @param $domain_id
     *
     * @return mixed
     */
    public function store($refererUrl, $host, $domain_id) {
        $attributes = [
            'url' => $refererUrl,
            'host' => $host,
            'log_domain_id' => $domain_id,
            'medium' => null,
            'source' => null,
            'search_terms_hash' => null,
        ];

        $parsed = $this->refererParser->parse($refererUrl, $this->currentUrl);
        if ($parsed->isKnown()) {
            $attributes['medium'] = $parsed->getMedium();
            $attributes['source'] = $parsed->getSource();
            $attributes['search_terms_hash'] = sha1($parsed->getSearchTerm());
        }

        $referer = $this->findOrCreate(
            $attributes,
            ['url', 'search_terms_hash']
        );

        $referer = $this->find($referer);
        if ($parsed->isKnown()) {
            $this->storeSearchTerms($referer, $parsed);
        }

        return $referer->log_referer_id;
    }

    private function storeSearchTerms($referer, $parsed) {
        foreach (explode(' ', $parsed->getSearchTerm()) as $term) {
            $this->findOrCreate(
                [
                    'log_referer_id' => $referer->log_referer_id,
                    'search_term' => $term,
                ],
                ['log_referer_id', 'search_term'],
                $created,
                $this->searchTermModel
            );
        }
    }
}
