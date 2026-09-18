<?php

defined('SYSPATH') or die('No direct access allowed.');

class CCache_TaggedCache extends CCache_Repository {
    use CCache_Trait_RetrievesMultipleKeys {
        putMany as putManyAlias;
    }

    /**
     * The tag set instance.
     *
     * @var CCache_TagSet
     */
    protected $tags;

    /**
     * Create a new tagged cache instance.
     *
     * @param CCache_DriverAbstract $driver
     * @param CCache_TagSet         $tags
     *
     * @return void
     */
    public function __construct(CCache_DriverAbstract $driver, CCache_TagSet $tags) {
        parent::__construct($driver);

        $this->tags = $tags;
    }

    /**
     * Store multiple items in the cache for a given number of seconds.
     *
     * @param array    $values
     * @param null|int $ttl
     *
     * @return bool
     */
    public function putMany(array $values, $ttl = null) {
        if ($ttl === null) {
            return $this->putManyForever($values);
        }

        return $this->putManyAlias($values, $ttl);
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return int|bool
     */
    public function increment($key, $value = 1) {
        return $this->driver->increment($this->itemKey($key), $value);
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return int|bool
     */
    public function decrement($key, $value = 1) {
        return $this->driver->decrement($this->itemKey($key), $value);
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush() {
        $this->tags->reset();

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function itemKey($key) {
        return $this->taggedItemKey($key);
    }

    /**
     * Get a fully qualified key for a tagged item.
     *
     * @param string $key
     *
     * @return string
     */
    public function taggedItemKey($key) {
        return sha1($this->tags->getNamespace()) . ':' . $key;
    }

    /**
     * Fire an event for this cache instance.
     *
     * @param string|object $event
     *
     * @return void
     */
    protected function event($event) {
        parent::event($event->setTags($this->tags->getNames()));
    }

    /**
     * Get the tag set instance.
     *
     * @return \CCache_TagSet
     */
    public function getTags() {
        return $this->tags;
    }
}
