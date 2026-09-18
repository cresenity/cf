<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 3:14:32 PM
 */
trait CTracker_RepositoryManager_RefererTrait {
    /**
     * @var CTracker_Repository_Referer
     */
    protected $refererRepository;

    protected function bootRefererTrait() {
        $this->refererRepository = new CTracker_Repository_Referer();
    }

    public function getRefererId($referer) {
        if ($referer) {
            $url = parse_url($referer);
            if (!isset($url['host'])) {
                return;
            }
            //            $parts = explode('.', $url['host']);
            //
            //            $domain = array_pop($parts);
            //            if (count($parts) > 0) {
            //                $domain = array_pop($parts) . '.' . $domain;
            //            }
            $domain = $url['host'];

            $domain_id = $this->getDomainId($domain);
            return $this->refererRepository->store($referer, $url['host'], $domain_id);
        }
    }
}
