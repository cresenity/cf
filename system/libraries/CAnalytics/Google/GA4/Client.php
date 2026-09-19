<?php

use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;

class CAnalytics_Google_GA4_Client {
    /**
     * @var BetaAnalyticsDataClient
     */
    protected $service;

    /**
     * @var \CCache_RepositoryInterface
     */
    protected $cache;

    /**
     * @var int
     */
    protected $cacheLifeTimeInMinutes = 0;

    public function __construct(BetaAnalyticsDataClient $service, CCache_RepositoryInterface $cache) {
        $this->service = $service;

        $this->cache = $cache;
    }

    /**
     * Set the cache time.
     *
     * @param int $cacheLifeTimeInMinutes
     *
     * @return self
     */
    public function setCacheLifeTimeInMinutes($cacheLifeTimeInMinutes) {
        $this->cacheLifeTimeInMinutes = $cacheLifeTimeInMinutes * 60;

        return $this;
    }

    /**
     * Query the Google Analytics Service with given parameters.
     *
     * @param mixed $propertyId
     * @param mixed $dateRanges
     * @param mixed $metrics
     * @param mixed $dimensions
     *
     * @return null|array
     */
    public function runReport($propertyId, $dateRanges, $metrics, $dimensions) {
        $cacheName = $this->determineCacheName(func_get_args());

        if ($this->cacheLifeTimeInMinutes === 0) {
            $this->cache->forget($cacheName);
        }

        return $this->cache->remember(
            $cacheName,
            $this->cacheLifeTimeInMinutes,
            function () use ($propertyId, $dateRanges, $metrics, $dimensions) {
                return $this->service->runReport(
                    [
                        'property' => 'properties/' . $propertyId,
                        'dateRanges' => [$dateRanges],
                        'dimensions' => $dimensions,
                        'metrics' => $metrics,
                    ]
                );
            }
        );
    }

    /**
     * @return BetaAnalyticsDataClient
     */
    public function getAnalyticsService() {
        return $this->service;
    }

    /**
     * Determine the cache name for the set of query properties given.
     *
     * @param mixed $properties
     */
    protected function determineCacheName($properties) {
        return 'capp.analytics.google.ga4.' . md5(serialize($properties));
    }
}
