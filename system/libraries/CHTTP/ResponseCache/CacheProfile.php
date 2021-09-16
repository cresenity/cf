<?php
use Symfony\Component\HttpFoundation\Response;

class CHTTP_ResponseCache_CacheProfile {
    protected $isEnabled;

    protected $isAddCacheTimeHeader;

    protected $cacheLifetimeInSeconds;

    protected $cacheNameSuffix;

    public function __construct() {
        $this->isEnabled = false;
        $this->cacheResponseEnabled = false;
        $this->isAddCacheTimeHeader = false;
        $this->cacheLifetimeInSeconds = CF::config('responsecache.cache_lifetime_in_seconds', 60 * 5);
        $this->cacheNameSuffix = '';
    }

    public function isAddCacheTimeHeader() {
        return $this->isAddCacheTimeHeader;
    }

    /**
     * @return static
     */
    public function addCacheTimeHeader() {
        return $this->setAddCacheTimeHeader(true);
    }

    /**
     * @param mixed $bool
     *
     * @return static
     */
    public function setAddCacheTimeHeader($bool) {
        $this->isAddCacheTimeHeader = $bool;
        return $this;
    }

    public function shouldCacheRequest(CHTTP_Request $request) {
        if ($request->ajax()) {
            return false;
        }

        if ($this->isRunningInConsole()) {
            return false;
        }

        return $request->isMethod('get');
    }

    /**
     * @param Response $response
     *
     * @return bool
     */
    public function shouldCacheResponse(Response $response) {
        if (!$this->hasCacheableResponseCode($response)) {
            return false;
        }

        if (!$this->hasCacheableContentType($response)) {
            return false;
        }

        return true;
    }

    public function isRunningInConsole() {
        return defined('CFCLI');
    }

    public function cacheRequestUntil(CHTTP_Request $request) {
        return CCarbon::now()->addSeconds($this->cacheLifetimeInSeconds);
    }

    public function cacheNameSuffix(CHTTP_Request $request) {
        return $this->cacheNameSuffix;
    }

    /**
     * @return bool
     */
    public function isEnabled() {
        return $this->isEnabled;
    }

    /**
     * @return void
     */
    public function enable() {
        $this->isEnabled = true;
    }

    /**
     * @param Response $response
     *
     * @return bool
     */
    public function hasCacheableResponseCode(Response $response) {
        if ($response->isSuccessful()) {
            return true;
        }

        if ($response->isRedirection()) {
            return true;
        }

        return false;
    }

    /**
     * @param Response $response
     *
     * @return bool
     */
    public function hasCacheableContentType(Response $response) {
        $contentType = $response->headers->get('Content-Type', '');

        if ($contentType == '') {
            return true;
        }
        if (cstr::startsWith($contentType, 'text/')) {
            return true;
        }

        if (cstr::contains($contentType, ['/json', '+json'])) {
            return true;
        }

        return false;
    }

    public function setCacheLifetime($seconds) {
        $this->cacheLifetimeInSeconds = $seconds;
        return $this;
    }

    public function setCacheNameSuffix($suffix) {
        $this->cacheNameSuffix = $suffix;
        return $this;
    }
}
