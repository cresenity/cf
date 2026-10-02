<?php

use Symfony\Component\HttpFoundation\Response;

class CHTTP_ResponseCache_Repository {
    protected $responseSerializer;

    /**
     * @var CCache_Repository
     */
    protected $cache;

    public function __construct($cache = null) {
        $this->responseSerializer = new CHTTP_ResponseCache_Serializer_DefaultSerializer();
        $this->cache = $cache;
    }

    /**
     * @param string        $key
     * @param Response      $response
     * @param \DateTime|int $seconds
     *
     * @return void
     */
    /**
     * Kunci penanda generasi untuk cache tanpa tag; clear() menaikkannya sehingga hanya entri response yang tak terbaca lagi.
     */
    const GENERATION_KEY = 'responsecache-generation';

    /**
     * @var null|int
     */
    protected $generation;

    /**
     * @return bool
     */
    protected function usesTags() {
        return $this->isTagged($this->cache);
    }

    /**
     * @return int
     */
    protected function currentGeneration() {
        if ($this->generation === null) {
            $this->generation = (int) $this->cache->get(static::GENERATION_KEY, 0);
        }

        return $this->generation;
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function storeKey($key) {
        return $this->usesTags() ? $key : $key . ':g' . $this->currentGeneration();
    }

    public function put($key, Response $response, $seconds) {
        if ($this->cache != null) {
            $this->cache->put($this->storeKey($key), $this->responseSerializer->serialize($response), is_numeric($seconds) ? c::now()->addSeconds($seconds) : $seconds);
        }
    }

    public function has($key) {
        if ($this->cache != null) {
            return $this->cache->has($this->storeKey($key));
        }

        return false;
    }

    public function get($key) {
        if ($this->cache != null) {
            $stored = $this->cache->get($this->storeKey($key));
            if ($stored === null) {
                return null;
            }

            return $this->responseSerializer->unserialize($stored);
        }

        return null;
    }

    public function clear() {
        if ($this->cache != null) {
            if ($this->usesTags()) {
                $this->cache->flush();

                return;
            }
            // tanpa tag, hanya entri response cache yang boleh tak terbaca - bukan seluruh store
            $this->generation = $this->currentGeneration() + 1;
            $this->cache->forever(static::GENERATION_KEY, $this->generation);
        }
    }

    public function forget($key) {
        if ($this->cache != null) {
            return $this->cache->forget($this->storeKey($key));
        }

        return false;
    }

    public function tags(array $tags) {
        if ($this->cache != null) {
            // @phpstan-ignore-next-line
            if ($this->cache instanceof CCache_TaggedCache && !empty($this->cache->getTags())) {
                $tags = array_merge($this->cache->getTags()->getNames(), $tags);
            }

            return new self($this->cache->tags($tags));
        }

        return null;
    }

    public function setCache(CCache_Repository $cache) {
        $this->cache = $cache;
        $this->generation = null;

        return $this;
    }

    /**
     * @return CCache_Repository
     */
    public function getCache() {
        return $this->cache;
    }

    public function hasCache() {
        return $this->cache != null;
    }

    /**
     * @param mixed $repository
     *
     * @return bool
     */
    public function isTagged($repository) {
        // @phpstan-ignore-next-line
        return $repository instanceof CCache_TaggedCache && !empty($repository->getTags());
    }
}
