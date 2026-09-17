<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @deprecated 1.8
 */
class CDetector_Crawler {
    /**
     * The user agent.
     *
     * @var null
     */
    protected $userAgent = null;

    /**
     * Headers that contain a user agent.
     *
     * @var array
     */
    protected $httpHeaders = [];

    /**
     * Store regex matches.
     *
     * @var array
     */
    protected $matches = [];

    /**
     * Crawlers object.
     *
     * @var \Jaybizzle\CrawlerDetect\Fixtures\Crawlers
     */
    protected $crawlers;

    /**
     * Exclusions object.
     *
     * @var \Jaybizzle\CrawlerDetect\Fixtures\Exclusions
     */
    protected $exclusions;

    /**
     * Headers object.
     *
     * @var \Jaybizzle\CrawlerDetect\Fixtures\Headers
     */
    protected $uaHttpHeaders;

    /**
     * The compiled regex string.
     *
     * @var string
     */
    protected $compiledRegex;

    /**
     * The compiled exclusions regex string.
     *
     * @var string
     */
    protected $compiledExclusions;

    /**
     * Class constructor.
     *
     * @param null|mixed $userAgent
     */
    public function __construct(array $headers = null, $userAgent = null) {
        $this->crawlers = new CDetector_Crawler_Provider_CrawlersProvider();
        $this->exclusions = new CDetector_Crawler_Provider_ExclusionsProvider();
        $this->uaHttpHeaders = new CDetector_Crawler_Provider_HeadersProvider();
        $this->compiledRegex = $this->compileRegex($this->crawlers->getAll());
        $this->compiledExclusions = $this->compileRegex($this->exclusions->getAll());
        $this->setHttpHeaders($headers);
        $this->setUserAgent($userAgent);
    }

    /**
     * Compile the regex patterns into one regex string.
     *
     * @param array $patterns
     *
     * @return string
     */
    public function compileRegex($patterns) {
        return '(' . implode('|', $patterns) . ')';
    }

    /**
     * Set HTTP headers.
     *
     * @param null|array $httpHeaders
     */
    public function setHttpHeaders($httpHeaders) {
        // Use global _SERVER if $httpHeaders aren't defined.
        if (!is_array($httpHeaders) || !count($httpHeaders)) {
            $httpHeaders = $_SERVER;
        }
        // Clear existing headers.
        $this->httpHeaders = [];
        // Only save HTTP headers. In PHP land, that means
        // only _SERVER vars that start with HTTP_.
        foreach ($httpHeaders as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $this->httpHeaders[$key] = $value;
            }
        }
    }

    /**
     * Return user agent headers.
     *
     * @return array
     */
    public function getUaHttpHeaders() {
        return $this->uaHttpHeaders->getAll();
    }

    /**
     * Set the user agent.
     *
     * @param string $userAgent
     */
    public function setUserAgent($userAgent) {
        if (is_null($userAgent)) {
            foreach ($this->getUaHttpHeaders() as $altHeader) {
                if (isset($this->httpHeaders[$altHeader])) {
                    $userAgent .= $this->httpHeaders[$altHeader] . ' ';
                }
            }
        }

        return $this->userAgent = $userAgent;
    }

    /**
     * Check user agent string against the regex.
     *
     * @param null|string $userAgent
     *
     * @return bool
     */
    public function isCrawler($userAgent = null) {
        $agent = trim(preg_replace(
            "/{$this->compiledExclusions}/i",
            '',
            $userAgent ?: $this->userAgent
        ));
        if ($agent == '') {
            return false;
        }
        $result = preg_match("/{$this->compiledRegex}/i", $agent, $matches);
        if ($matches) {
            $this->matches = $matches;
        }

        return (bool) $result;
    }

    /**
     * Return the matches.
     *
     * @return null|string
     */
    public function getMatches() {
        return isset($this->matches[0]) ? $this->matches[0] : null;
    }
}
