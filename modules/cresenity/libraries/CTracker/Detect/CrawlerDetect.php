<?php

defined('SYSPATH') OR die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @since Jun 23, 2019, 4:15:51 AM
 * @license Ittron Global Teknologi <ittron.co.id>
 */
class CTracker_Detect_CrawlerDetect extends CDetector_Crawler {

    /**
     * Crawler detector.
     *
     * @var \Jaybizzle\CrawlerDetect\CrawlerDetect
     */
    private $detector;

    /**
     * Instantiate detector.
     *
     * @param array $headers
     * @param $agent
     */
    public function __construct(array $headers, $agent) {
        $this->detector = new CDetector_Crawler($headers, $agent);
    }

    /**
     * Check if current request is from a bot.
     *
     * @return bool
     */
    public function isRobot() {
        return $this->detector->isCrawler();
    }

}
