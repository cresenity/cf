<?php

/**
 * Description of Cookie.
 */
use Symfony\Component\HttpFoundation\Cookie;

class CHTTP_Cookie implements CHTTP_Contract_CookieInterface {
    use CTrait_Helper_InteractsWithTime,
        CTrait_Macroable;

    /**
     * The default path (if specified).
     *
     * @var string
     */
    protected $path = '/';

    /**
     * The default domain (if specified).
     *
     * @var string
     */
    protected $domain;

    /**
     * The default secure setting (defaults to null).
     *
     * @var null|bool
     */
    protected $secure;

    /**
     * The default SameSite option (defaults to lax).
     *
     * @var string
     */
    protected $sameSite = 'lax';

    /**
     * The default partitioned (CHIPS) setting (defaults to false).
     *
     * @var bool
     */
    protected $partitioned = false;

    /**
     * All of the cookies queued for sending.
     *
     * @var array<string, array<string, \Symfony\Component\HttpFoundation\Cookie>>
     */
    protected $queued = [];

    /**
     * Create a new cookie instance.
     *
     * @param string      $name
     * @param string      $value
     * @param int         $minutes
     * @param null|string $path
     * @param null|string $domain
     * @param null|bool   $secure
     * @param bool        $httpOnly
     * @param bool        $raw
     * @param null|string $sameSite
     * @param null|bool   $partitioned
     *
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function make($name, $value, $minutes = 0, $path = null, $domain = null, $secure = null, $httpOnly = true, $raw = false, $sameSite = null, $partitioned = null) {
        list($path, $domain, $secure, $sameSite) = $this->getPathAndDomain($path, $domain, $secure, $sameSite);

        $time = ($minutes == 0) ? 0 : $this->availableAt($minutes * 60);

        return new Cookie($name, $value, $time, $path, $domain, $secure, $httpOnly, $raw, $sameSite, is_bool($partitioned) ? $partitioned : $this->partitioned);
    }

    /**
     * Create a cookie that lasts "forever" (five years).
     *
     * @param string      $name
     * @param string      $value
     * @param null|string $path
     * @param null|string $domain
     * @param null|bool   $secure
     * @param bool        $httpOnly
     * @param bool        $raw
     * @param null|string $sameSite
     * @param null|bool   $partitioned
     *
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function forever($name, $value, $path = null, $domain = null, $secure = null, $httpOnly = true, $raw = false, $sameSite = null, $partitioned = null) {
        return $this->make($name, $value, 2628000, $path, $domain, $secure, $httpOnly, $raw, $sameSite, $partitioned);
    }

    /**
     * Expire the given cookie.
     *
     * @param string      $name
     * @param null|string $path
     * @param null|string $domain
     *
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function forget($name, $path = null, $domain = null) {
        return $this->make($name, null, -2628000, $path, $domain);
    }

    /**
     * Determine if a cookie has been queued.
     *
     * @param string      $key
     * @param null|string $path
     *
     * @return bool
     */
    public function hasQueued($key, $path = null) {
        return !is_null($this->queued($key, null, $path));
    }

    /**
     * Get a queued cookie instance.
     *
     * @param string      $key
     * @param mixed       $default
     * @param null|string $path
     *
     * @return null|\Symfony\Component\HttpFoundation\Cookie
     */
    public function queued($key, $default = null, $path = null) {
        $queued = carr::get($this->queued, $key, $default);

        if ($path === null) {
            return carr::last(carr::wrap($queued), null, $default);
        }

        return carr::get($queued, $path, $default);
    }

    /**
     * Queue a cookie to send with the next response.
     *
     * @param array $parameters
     *
     * @return void
     */
    public function queue(...$parameters) {
        if (isset($parameters[0]) && $parameters[0] instanceof Cookie) {
            $cookie = $parameters[0];
        } else {
            $cookie = $this->make(...array_values($parameters));
        }

        if (!isset($this->queued[$cookie->getName()])) {
            $this->queued[$cookie->getName()] = [];
        }

        $this->queued[$cookie->getName()][$cookie->getPath()] = $cookie;
    }

    /**
     * Remove a cookie from the queue.
     *
     * @param string      $name
     * @param null|string $path
     *
     * @return void
     */
    public function unqueue($name, $path = null) {
        if ($path === null) {
            unset($this->queued[$name]);

            return;
        }

        unset($this->queued[$name][$path]);

        if (empty($this->queued[$name])) {
            unset($this->queued[$name]);
        }
    }

    /**
     * Get the path and domain, or the default values.
     *
     * @param string      $path
     * @param string      $domain
     * @param null|bool   $secure
     * @param null|string $sameSite
     *
     * @return array
     */
    protected function getPathAndDomain($path, $domain, $secure = null, $sameSite = null) {
        return [$path ?: $this->path, $domain ?: $this->domain, is_bool($secure) ? $secure : $this->secure, $sameSite ?: $this->sameSite];
    }

    /**
     * Set the default path and domain for the jar.
     *
     * @param string      $path
     * @param string      $domain
     * @param bool        $secure
     * @param null|string $sameSite
     * @param bool        $partitioned
     *
     * @return $this
     */
    public function setDefaultPathAndDomain($path, $domain, $secure = false, $sameSite = null, $partitioned = false) {
        list($this->path, $this->domain, $this->secure, $this->sameSite, $this->partitioned) = [$path, $domain, $secure, $sameSite, $partitioned];

        return $this;
    }

    /**
     * Get the cookies which have been queued for the next request.
     *
     * @return \Symfony\Component\HttpFoundation\Cookie[]
     */
    public function getQueuedCookies() {
        return carr::flatten($this->queued);
    }
}
