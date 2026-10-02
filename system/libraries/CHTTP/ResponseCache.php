<?php
use Symfony\Component\HttpFoundation\Response;

final class CHTTP_ResponseCache {
    protected $callback;

    protected $hasher;

    protected $cache;

    /**
     * Cache Profile.
     *
     * @var CHTTP_ResponseCache_CacheProfile
     */
    protected $cacheProfile;

    /**
     * Singleton Instance.
     *
     * @var CHTTP_ResponseCache
     */
    private static $instance;

    /**
     * @return CHTTP_ResponseCache
     */
    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct() {
        $this->cacheProfile = new CHTTP_ResponseCache_CacheProfile();
    }

    public function enable() {
        $this->cacheProfile->enable();

        $this->getHasher();
        $this->getCache();

        return $this;
    }

    public function isEnabled() {
        return $this->cacheProfile->isEnabled();
    }

    public function shouldCache(CHTTP_Request $request, Response $response) {
        if ($request->attributes->has('responsecache.doNotCache')) {
            return false;
        }

        if (!$this->cacheProfile->shouldCacheRequest($request)) {
            return false;
        }

        return $this->cacheProfile->shouldCacheResponse($response);
    }

    public function hasBeenCached(CHTTP_Request $request, array $tags = []) {
        return $this->hasCache() ? $this->taggedCache($tags)->has($this->getHasher()->getHashFor($request))
            : false;
    }

    /**
     * @param CHTTP_Request $request
     * @param array         $tags
     *
     * @return Response
     */
    public function getCachedResponseFor(CHTTP_Request $request, array $tags = []) {
        return $this->taggedCache($tags)->get($this->getHasher()->getHashFor($request));
    }

    /**
     * @param array $tags
     *
     * @return CHTTP_ResponseCache_Repository
     */
    protected function taggedCache(array $tags = []) {
        if (empty($tags)) {
            return $this->getCache();
        }

        return $this->getCache()->tags($tags);
    }

    /**
     * Undocumented function.
     *
     * @param CHTTP_Request $request
     * @param Response      $response
     *
     * @return void
     */
    public function makeReplacementsAndCacheResponse(
        CHTTP_Request $request,
        Response $response
    ) {
        $cachedResponse = clone $response;

        //$this->getReplacers()->each(fn (Replacer $replacer) => $replacer->prepareResponseToCache($cachedResponse));

        $this->cacheResponse($request, $cachedResponse);
    }

    /**
     * @param CHTTP_Request $request
     * @param Response      $response
     *
     * @return Response
     */
    public function cacheResponse(
        CHTTP_Request $request,
        Response $response
    ) {
        if ($this->cacheProfile->isAddCacheTimeHeader()) {
            $response = $this->addCachedHeader($response);
        }

        $this->getCache()->put(
            $this->getHasher()->getHashFor($request),
            $response,
            $this->cacheProfile->cacheRequestUntil($request)
        );

        return $response;
    }

    protected function addCachedHeader(Response $response) {
        $clonedResponse = clone $response;

        $clonedResponse->headers->set(
            CF::config('responsecache.cache_time_header_name', 'capp-responsecache'),
            CCarbon::now()->toRfc2822String()
        );

        return $clonedResponse;
    }

    /**
     * @return CHTTP_ResponseCache_Repository
     */
    public function getCache() {
        if ($this->cache == null) {
            $this->cache = new CHTTP_ResponseCache_Repository();
        }

        return $this->cache;
    }

    /**
     * @return CHTTP_ResponseCache_Hasher_DefaultHasher
     */
    protected function getHasher() {
        if ($this->hasher == null) {
            $this->hasher = new CHTTP_ResponseCache_Hasher_DefaultHasher($this->cacheProfile);
        }

        return $this->hasher;
    }

    public function useCache(CCache_Repository $cache) {
        $this->getCache()->setCache($cache);

        return $this;
    }

    public function withCacheProfile($callback) {
        if (is_callable($callback)) {
            call_user_func_array($callback, [$this->cacheProfile]);
        }

        return $this;
    }

    public function selectCachedItems() {
        return new CHTTP_ResponseCache_CacheItemSelector($this->getHasher(), $this->getCache());
    }

    /**
     * @param array $tags
     *
     * @return void
     */
    public function clear($tags = []) {
        $this->taggedCache($tags)->clear();
    }

    /**
     * @param string|array $uris
     * @param string[]     $tags
     *
     * @return $this
     */
    public function forget($uris, array $tags = []) {
        $uris = is_array($uris) ? $uris : func_get_args();
        $this->selectCachedItems()->forUrls($uris)->forget();

        return $this;
    }

    public function cacheProfile() {
        return $this->cacheProfile;
    }

    public function hasCache() {
        if ($this->cache == null) {
            return false;
        }

        return $this->getCache()->hasCache();
    }
}
