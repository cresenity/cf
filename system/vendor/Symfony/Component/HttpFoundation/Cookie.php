<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * Represents a cookie.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Cookie {
    const SAMESITE_NONE = 'none';

    const SAMESITE_LAX = 'lax';

    const SAMESITE_STRICT = 'strict';

    const RESERVED_CHARS_FROM = ['=', ',', ';', ' ', "\t", "\r", "\n", "\v", "\f"];

    const RESERVED_CHARS_TO = ['%3D', '%2C', '%3B', '%20', '%09', '%0D', '%0A', '%0B', '%0C'];

    protected $name;

    protected $value;

    protected $domain;

    protected $expire;

    protected $path;

    protected $secure;

    protected $httpOnly;

    private $raw;

    private $sameSite;

    private $partitioned;

    private $secureDefault = false;

    private static $reservedCharsList = "=,; \t\r\n\v\f";

    /**
     * Creates cookie from raw header string.
     *
     * @param string $cookie
     * @param bool   $decode
     *
     * @return static
     */
    public static function fromString($cookie, $decode = false) {
        $data = [
            'expires' => 0,
            'path' => '/',
            'domain' => null,
            'secure' => false,
            'httponly' => false,
            'raw' => !$decode,
            'samesite' => null,
            'partitioned' => false,
        ];

        $parts = HeaderUtils::split($cookie, ';=');
        $part = array_shift($parts);

        $name = $decode ? urldecode($part[0]) : $part[0];
        $value = isset($part[1]) ? ($decode ? urldecode($part[1]) : $part[1]) : null;

        $data = HeaderUtils::combine($parts) + $data;
        $data['expires'] = self::expiresTimestamp($data['expires']);

        if (isset($data['max-age']) && ($data['max-age'] > 0 || $data['expires'] > time())) {
            $data['expires'] = time() + (int) $data['max-age'];
        }

        return new static($name, $value, $data['expires'], $data['path'], $data['domain'], $data['secure'], $data['httponly'], $data['raw'], $data['samesite'], $data['partitioned']);
    }

    /**
     * @param string      $name
     * @param null|string $value
     * @param int         $expire
     * @param null|string $path
     * @param null|string $domain
     * @param null|bool   $secure
     * @param bool        $httpOnly
     * @param bool        $raw
     * @param null|string $sameSite
     * @param bool        $partitioned
     *
     * @return self
     */
    public static function create($name, $value = null, $expire = 0, $path = '/', $domain = null, $secure = null, $httpOnly = true, $raw = false, $sameSite = self::SAMESITE_LAX, $partitioned = false) {
        return new self($name, $value, $expire, $path, $domain, $secure, $httpOnly, $raw, $sameSite, $partitioned);
    }

    /**
     * @param string                        $name     The name of the cookie
     * @param null|string                   $value    The value of the cookie
     * @param int|string|\DateTimeInterface $expire   The time the cookie expires
     * @param string                        $path     The path on the server in which the cookie will be available on
     * @param null|string                   $domain   The domain that the cookie is available to
     * @param null|bool                     $secure   Whether the client should send back the cookie only over HTTPS or null to auto-enable this when the request is already using HTTPS
     * @param bool                          $httpOnly Whether the cookie will be made accessible only through the HTTP protocol
     * @param bool                          $raw      Whether the cookie value should be sent with no url encoding
     * @param null|string                   $sameSite    Whether the cookie will be available for cross-site requests
     * @param bool                          $partitioned Whether the cookie is tied to the top-level site in a cross-site context (CHIPS)
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($name, $value = null, $expire = 0, $path = '/', $domain = null, $secure = null, $httpOnly = true, $raw = false, $sameSite = 'lax', $partitioned = false) {
        // from PHP source code
        if ($raw && false !== strpbrk($name, self::$reservedCharsList)) {
            throw new \InvalidArgumentException(sprintf('The cookie name "%s" contains invalid characters.', $name));
        }

        if (empty($name)) {
            throw new \InvalidArgumentException('The cookie name cannot be empty.');
        }

        $this->name = $name;
        $this->value = $value;
        $this->domain = $domain;
        $this->expire = self::expiresTimestamp($expire);
        $this->path = empty($path) ? '/' : $path;
        $this->secure = $secure;
        $this->httpOnly = $httpOnly;
        $this->raw = $raw;
        $this->sameSite = $this->withSameSite($sameSite)->sameSite;
        $this->partitioned = $partitioned;
    }

    /**
     * Creates a cookie copy with a new value.
     *
     * @param null|string $value
     *
     * @return static
     */
    public function withValue($value = null) {
        $cookie = clone $this;
        $cookie->value = $value;

        return $cookie;
    }

    /**
     * Creates a cookie copy with a new domain that the cookie is available to.
     *
     * @param null|mixed $domain
     *
     * @return static
     */
    public function withDomain($domain = null) {
        $cookie = clone $this;
        $cookie->domain = $domain;

        return $cookie;
    }

    /**
     * Creates a cookie copy with a new time the cookie expires.
     *
     * @param int|string|\DateTimeInterface $expire
     *
     * @return static
     */
    public function withExpires($expire = 0) {
        $cookie = clone $this;
        $cookie->expire = self::expiresTimestamp($expire);

        return $cookie;
    }

    /**
     * Converts expires formats to a unix timestamp.
     *
     * @param int|string|\DateTimeInterface $expire
     *
     * @return int
     */
    private static function expiresTimestamp($expire = 0) {
        // convert expiration time to a Unix timestamp
        if ($expire instanceof \DateTimeInterface) {
            $expire = $expire->format('U');
        } elseif (!is_numeric($expire)) {
            $expire = strtotime($expire);

            if (false === $expire) {
                throw new \InvalidArgumentException('The cookie expiration time is not valid.');
            }
        }

        return 0 < $expire ? (int) $expire : 0;
    }

    /**
     * Creates a cookie copy with a new path on the server in which the cookie will be available on.
     *
     * @param string $path
     *
     * @return static
     */
    public function withPath($path) {
        $cookie = clone $this;
        $cookie->path = '' === $path ? '/' : $path;

        return $cookie;
    }

    /**
     * Creates a cookie copy that only be transmitted over a secure HTTPS connection from the client.
     *
     * @param bool $secure
     *
     * @return static
     */
    public function withSecure($secure = true) {
        $cookie = clone $this;
        $cookie->secure = $secure;

        return $cookie;
    }

    /**
     * Creates a cookie copy that be accessible only through the HTTP protocol.
     *
     * @param bool $httpOnly
     *
     * @return static
     */
    public function withHttpOnly($httpOnly = true) {
        $cookie = clone $this;
        $cookie->httpOnly = $httpOnly;

        return $cookie;
    }

    /**
     * Creates a cookie copy that uses no url encoding.
     *
     * @param bool $raw
     *
     * @return static
     */
    public function withRaw($raw = true) {
        if ($raw && false !== strpbrk($this->name, self::$reservedCharsList)) {
            throw new \InvalidArgumentException(sprintf('The cookie name "%s" contains invalid characters.', $this->name));
        }

        $cookie = clone $this;
        $cookie->raw = $raw;

        return $cookie;
    }

    /**
     * Creates a cookie copy with SameSite attribute.
     *
     * @param null|string $sameSite
     *
     * @return static
     */
    public function withSameSite($sameSite = null) {
        if ('' === $sameSite) {
            $sameSite = null;
        } elseif (null !== $sameSite) {
            $sameSite = strtolower($sameSite);
        }

        if (!\in_array($sameSite, [self::SAMESITE_LAX, self::SAMESITE_STRICT, self::SAMESITE_NONE, null], true)) {
            throw new \InvalidArgumentException('The "sameSite" parameter value is not valid.');
        }

        $cookie = clone $this;
        $cookie->sameSite = $sameSite;

        return $cookie;
    }

    /**
     * Creates a cookie copy that is tied to the top-level site in cross-site
     * contexts (CHIPS - Cookies Having Independent Partitioned State).
     *
     * @param bool $partitioned
     *
     * @return static
     */
    public function withPartitioned($partitioned = true) {
        $cookie = clone $this;
        $cookie->partitioned = $partitioned;

        return $cookie;
    }

    /**
     * Returns the cookie as a string.
     *
     * @return string The cookie
     */
    public function __toString() {
        if ($this->isRaw()) {
            $str = $this->getName();
        } else {
            $str = str_replace(self::RESERVED_CHARS_FROM, self::RESERVED_CHARS_TO, $this->getName());
        }

        $str .= '=';

        if ('' === (string) $this->getValue()) {
            $str .= 'deleted; expires=' . gmdate('D, d-M-Y H:i:s T', time() - 31536001) . '; Max-Age=0';
        } else {
            $str .= $this->isRaw() ? $this->getValue() : rawurlencode($this->getValue());

            if (0 !== $this->getExpiresTime()) {
                $str .= '; expires=' . gmdate('D, d-M-Y H:i:s T', $this->getExpiresTime()) . '; Max-Age=' . $this->getMaxAge();
            }
        }

        if ($this->getPath()) {
            $str .= '; path=' . $this->getPath();
        }

        if ($this->getDomain()) {
            $str .= '; domain=' . $this->getDomain();
        }

        if (true === $this->isSecure()) {
            $str .= '; secure';
        }

        if (true === $this->isHttpOnly()) {
            $str .= '; httponly';
        }

        if (null !== $this->getSameSite()) {
            $str .= '; samesite=' . $this->getSameSite();
        }

        if (true === $this->isPartitioned()) {
            $str .= '; partitioned';
        }

        return $str;
    }

    /**
     * Gets the name of the cookie.
     *
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Gets the value of the cookie.
     *
     * @return string
     */
    public function getValue() {
        return $this->value;
    }

    /**
     * Gets the domain that the cookie is available to.
     *
     * @return string
     */
    public function getDomain() {
        return $this->domain;
    }

    /**
     * Gets the time the cookie expires.
     *
     * @return int
     */
    public function getExpiresTime() {
        return $this->expire;
    }

    /**
     * Gets the max-age attribute.
     *
     * @return int
     */
    public function getMaxAge() {
        $maxAge = $this->expire - time();

        return 0 >= $maxAge ? 0 : $maxAge;
    }

    /**
     * Gets the path on the server in which the cookie will be available on.
     *
     * @return string
     */
    public function getPath() {
        return $this->path;
    }

    /**
     * Checks whether the cookie should only be transmitted over a secure HTTPS connection from the client.
     *
     * @return bool
     */
    public function isSecure() {
        return $this->secure ?: $this->secureDefault;
    }

    /**
     * Checks whether the cookie will be made accessible only through the HTTP protocol.
     *
     * @return bool
     */
    public function isHttpOnly() {
        return $this->httpOnly;
    }

    /**
     * Whether this cookie is about to be cleared.
     *
     * @return bool
     */
    public function isCleared() {
        return 0 !== $this->expire && $this->expire < time();
    }

    /**
     * Checks if the cookie value should be sent with no url encoding.
     *
     * @return bool
     */
    public function isRaw() {
        return $this->raw;
    }

    /**
     * Gets the SameSite attribute.
     *
     * @return null|string
     */
    public function getSameSite() {
        return $this->sameSite;
    }

    /**
     * Checks whether the cookie is tied to the top-level site in a
     * cross-site context (CHIPS).
     *
     * @return bool
     */
    public function isPartitioned() {
        return $this->partitioned;
    }

    /**
     * @param bool $default The default value of the "secure" flag when it is set to null
     */
    public function setSecureDefault($default) {
        $this->secureDefault = $default;
    }
}
