<?php

defined('SYSPATH') or die('No direct access allowed.');

use Illuminate\Contracts\Support\Arrayable;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;

class CHTTP_Request extends SymfonyRequest implements Arrayable, ArrayAccess {
    use CHTTP_Trait_InteractsWithInput,
        CHTTP_Trait_InteractsWithContentTypes,
        CHTTP_Trait_InteractsWithFlashData;

    /**
     * The CBrowser instance.
     *
     * @var null|CBrowser
     */
    protected $browser;

    /**
     * The Accept header the acceptable content types were last calculated from.
     *
     * @var null|string
     */
    protected $cachedAcceptHeader;

    /**
     * The decoded JSON content for the request.
     *
     * @var null|\Symfony\Component\HttpFoundation\ParameterBag
     */
    protected $json;

    /**
     * All of the converted files for the request.
     *
     * @var array
     */
    protected $convertedFiles;

    /**
     * The user resolver callback.
     *
     * @var null|\Closure
     */
    protected $userResolver;

    /**
     * The route resolver callback.
     *
     * @var null|\Closure
     */
    protected $routeResolver;

    /**
     * Create a new HTTP request from server variables.
     *
     * @return static
     *
     * @phpstan-return static(CHTTP_Request)
     */
    public static function capture() {
        static::enableHttpMethodParameterOverride();

        return static::createFromBase(SymfonyRequest::createFromGlobals());
    }

    /**
     * Return the Request instance.
     *
     * @return $this
     */
    public function instance() {
        return $this;
    }

    /**
     * Get the request method.
     *
     * @return string
     */
    public function method() {
        return $this->getMethod();
    }

    /**
     * Get the root URL for the application.
     *
     * @return string
     */
    public function root() {
        $schemeAndHttpHost = $this->getSchemeAndHttpHost();
        if (cstr::endsWith($schemeAndHttpHost, ':')) {
            //remove last char
            $schemeAndHttpHost = substr($schemeAndHttpHost, 0, -1);
        }

        return rtrim($schemeAndHttpHost . $this->getBaseUrl(), '/');
    }

    public function getHost() {
        $host = parent::getHost();
        if ($host == null) {
            $host = CF::domain();
        }

        return $host;
    }

    /**
     * Get the host name.
     *
     * @return string
     */
    public function host() {
        return $this->getHost();
    }

    /**
     * Get the HTTP host being requested.
     *
     * @return string
     */
    public function httpHost() {
        return $this->getHttpHost();
    }

    /**
     * Get the scheme and HTTP host.
     *
     * @return string
     */
    public function schemeAndHttpHost() {
        return $this->getSchemeAndHttpHost();
    }

    /**
     * Get the URL (no query string) for the request.
     *
     * @return string
     */
    public function url() {
        return rtrim(preg_replace('/\?.*/', '', $this->getUri()), '/');
    }

    /**
     * Get the full URL for the request.
     *
     * @return string
     */
    public function fullUrl() {
        $query = $this->getQueryString();

        $question = $this->getBaseUrl() . $this->getPathInfo() === '/' ? '/?' : '?';

        return $query ? $this->url() . $question . $query : $this->url();
    }

    /**
     * Get the full URL for the request with the added query string parameters.
     *
     * @return string
     */
    public function fullUrlWithQuery(array $query) {
        $question = $this->getBaseUrl() . $this->getPathInfo() === '/' ? '/?' : '?';

        return count($this->query()) > 0 ? $this->url() . $question . carr::query(array_merge($this->query(), $query)) : $this->fullUrl() . $question . carr::query($query);
    }

    /**
     * Get the full URL for the request without the given query string parameters.
     *
     * @param array|string $keys
     *
     * @return string
     */
    public function fullUrlWithoutQuery($keys) {
        $query = carr::except($this->query(), (array) $keys);

        $question = $this->getBaseUrl() . $this->getPathInfo() === '/' ? '/?' : '?';

        return count($query) > 0 ? $this->url() . $question . carr::query($query) : $this->url();
    }

    /**
     * Get the current path info for the request.
     *
     * @return string
     */
    public function path() {
        $pattern = trim($this->getPathInfo(), '/');

        return $pattern == '' ? '/' : $pattern;
    }

    /**
     * Get the current decoded path info for the request.
     *
     * @return string
     */
    public function decodedPath() {
        return rawurldecode($this->path());
    }

    /**
     * Get a segment from the URI (1 based index).
     *
     * @param int         $index
     * @param null|string $default
     *
     * @return null|string
     */
    public function segment($index, $default = null) {
        return carr::get($this->segments(), $index - 1, $default);
    }

    /**
     * Get all of the segments for the request path.
     *
     * @return array
     */
    public function segments() {
        $segments = explode('/', $this->decodedPath());

        return array_values(array_filter($segments, function ($value) {
            return $value !== '';
        }));
    }

    /**
     * Determine if the current request URI matches a pattern.
     *
     * @param mixed ...$patterns
     *
     * @return bool
     */
    public function is(...$patterns) {
        $path = $this->decodedPath();

        foreach ($patterns as $pattern) {
            if (cstr::is($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the route name matches a given pattern.
     *
     * @param mixed ...$patterns
     *
     * @return bool
     */
    public function routeIs(...$patterns) {
        return $this->route() && $this->route()->named(...$patterns);
    }

    /**
     * Determine if the current request URL and query string matches a pattern.
     *
     * @param mixed ...$patterns
     *
     * @return bool
     */
    public function fullUrlIs(...$patterns) {
        $url = $this->fullUrl();

        foreach ($patterns as $pattern) {
            if (cstr::is($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the request is the result of an AJAX call.
     *
     * @return bool
     */
    public function ajax() {
        return $this->isXmlHttpRequest();
    }

    /**
     * Determine if the request is the result of an PJAX call.
     *
     * @return bool
     */
    public function pjax() {
        return $this->headers->get('X-PJAX') == true;
    }

    /**
     * Determine if the request is the result of an prefetch call.
     *
     * @return bool
     */
    public function prefetch() {
        return strcasecmp((string) $this->server->get('HTTP_X_MOZ'), 'prefetch') === 0
                || strcasecmp((string) $this->headers->get('Purpose'), 'prefetch') === 0;
    }

    /**
     * Determine if the request is over HTTPS.
     *
     * @return bool
     */
    public function secure() {
        return $this->isSecure();
    }

    /**
     * Get the client IP address.
     *
     * @return null|string
     */
    public function ip() {
        return $this->getClientIp();
    }

    /**
     * Get the client IP addresses.
     *
     * @return array
     */
    public function ips() {
        return $this->getClientIps();
    }

    /**
     * Get the client user agent.
     *
     * @return null|string
     */
    public function userAgent() {
        return $this->headers->get('User-Agent');
    }

    /**
     * Merge new input into the current request's input array.
     *
     * @return $this
     */
    public function merge(array $input) {
        $this->getInputSource()->add($input);

        return $this;
    }

    /**
     * Merge new input into the request's input, but only when that key is missing from the request.
     *
     * @param array $input
     *
     * @return $this
     */
    public function mergeIfMissing(array $input) {
        return $this->merge(c::collect($input)->filter(function ($value, $key) {
            return $this->missing($key);
        })->toArray());
    }

    /**
     * Get the acceptable content types, recalculated when the Accept header changed since the last read.
     *
     * @return array
     */
    public function getAcceptableContentTypes() {
        $currentAcceptHeader = $this->headers->get('Accept');

        if ($this->cachedAcceptHeader !== $currentAcceptHeader) {
            $this->acceptableContentTypes = null;
            $this->cachedAcceptHeader = $currentAcceptHeader;
        }

        return parent::getAcceptableContentTypes();
    }

    /**
     * Replace the input for the current request.
     *
     * @return $this
     */
    public function replace(array $input) {
        $this->getInputSource()->replace($input);

        return $this;
    }

    /**
     * This method belongs to Symfony HttpFoundation and is not usually needed.
     *
     * Instead, you may use the "input" method.
     *
     * @param string $key
     * @param mixed  $default
     * @param mixed  $deep
     *
     * @return mixed
     */
    public function get($key, $default = null, $deep = false) {
        return parent::get($key, $default, $deep);
    }

    /**
     * Get the JSON payload for the request.
     *
     * @param null|string $key
     * @param mixed       $default
     *
     * @return \Symfony\Component\HttpFoundation\ParameterBag|mixed
     */
    public function json($key = null, $default = null) {
        if (!isset($this->json)) {
            $this->json = new ParameterBag((array) json_decode($this->getContent(), true));
        }

        if (is_null($key)) {
            return $this->json;
        }

        return c::get($this->json->all(), $key, $default);
    }

    /**
     * Get the input source for the request.
     *
     * @return \Symfony\Component\HttpFoundation\ParameterBag
     */
    protected function getInputSource() {
        if ($this->isJson()) {
            return $this->json();
        }

        return in_array($this->getRealMethod(), ['GET', 'HEAD']) ? $this->query : $this->request;
    }

    /**
     * Create a new request instance from the given request.
     *
     * @param null|\CHTTP_Request $to
     *
     * @return static
     *
     * @phpstan-return CHTTP_Request
     */
    public static function createFrom(self $from, $to = null) {
        //@phpstan-ignore-next-line
        $request = $to ?: new static();

        $files = $from->files->all();

        $files = is_array($files) ? array_filter($files) : $files;

        $request->initialize(
            $from->query->all(),
            $from->request->all(),
            $from->attributes->all(),
            $from->cookies->all(),
            $files,
            $from->server->all(),
            $from->getContent()
        );

        $request->headers->replace($from->headers->all());

        $request->setJson($from->json());

        $request->setUserResolver($from->getUserResolver());

        $request->setRouteResolver($from->getRouteResolver());

        return $request;
    }

    /**
     * Create an request from a Symfony instance.
     *
     * @return static
     *
     * @phpstan-return static(CHTTP_Request)
     */
    public static function createFromBase(SymfonyRequest $request) {
        //@phpstan-ignore-next-line
        $newRequest = (new static())->duplicate(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all()
        );

        $newRequest->headers->replace($request->headers->all());

        $newRequest->content = $request->content;

        $newRequest->request = $newRequest->getInputSource();

        return $newRequest;
    }

    /**
     * @inheritdoc
     */
    public function duplicate(?array $query = null, ?array $request = null, ?array $attributes = null, ?array $cookies = null, ?array $files = null, ?array $server = null) {
        return parent::duplicate($query, $request, $attributes, $cookies, $this->filterFiles($files), $server);
    }

    /**
     * Filter the given array of files, removing any empty values.
     *
     * @param mixed $files
     *
     * @return mixed
     */
    protected function filterFiles($files) {
        if (!$files) {
            return;
        }

        foreach ($files as $key => $file) {
            if (is_array($file)) {
                $files[$key] = $this->filterFiles($files[$key]);
            }

            if (empty($files[$key])) {
                unset($files[$key]);
            }
        }

        return $files;
    }

    /**
     * Gets the Session.
     *
     * @return null|SessionInterface The session
     */
    public function getSession() {
        $session = $this->session();
        if ($session) {
            return new CSession_SymfonySessionDecorator($session);
        }

        throw new SessionNotFoundException();
    }

    /**
     * Determine if a session is available; CF keeps it in CBase, not on the Symfony request.
     *
     * @return bool
     */
    public function hasSession() {
        return parent::hasSession() || CBase::session() !== null;
    }

    /**
     * Get the session associated with the request.
     *
     * @throws \RuntimeException
     *
     * @return \CSession_Store
     */
    public function session() {
        return CBase::session();
    }

    /**
     * Get the user making the request.
     *
     * @param null|string $guard
     *
     * @return mixed
     */
    public function user($guard = null) {
        return call_user_func($this->getUserResolver(), $guard);
    }

    /**
     * Get the route handling the request.
     *
     * @param null|string $param
     * @param mixed       $default
     *
     * @return null|\CRouting_Route|object|string
     *
     * @phpstan-return ($param is null ? CRouting_Route|null : object|string|null)
     */
    public function route($param = null, $default = null) {
        $route = call_user_func($this->getRouteResolver());

        if (is_null($route) || is_null($param)) {
            return $route;
        }

        return $route->parameter($param, $default);
    }

    /**
     * Get a unique fingerprint for the request / route / IP address.
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    public function fingerprint() {
        if (!$route = $this->route()) {
            throw new RuntimeException('Unable to generate fingerprint. Route unavailable.');
        }

        return sha1(implode('|', array_merge(
            $route->methods(),
            [$route->getDomain(), $route->uri(), $this->ip()]
        )));
    }

    /**
     * Set the JSON payload for the request.
     *
     * @param \Symfony\Component\HttpFoundation\ParameterBag $json
     *
     * @return $this
     */
    public function setJson($json) {
        $this->json = $json;

        return $this;
    }

    /**
     * Get the user resolver callback.
     *
     * @return \Closure
     */
    public function getUserResolver() {
        return $this->userResolver ?: function ($guard = null) {
            if ($guard == null) {
                $guard = c::app()->auth()->guardName();
            }

            return c::auth($guard)->user();
        };
    }

    /**
     * Set the user resolver callback.
     *
     * @return $this
     */
    public function setUserResolver(Closure $callback) {
        $this->userResolver = $callback;

        return $this;
    }

    /**
     * Get the route resolver callback.
     *
     * @return \Closure
     */
    public function getRouteResolver() {
        return $this->routeResolver ?: function () {
        };
    }

    /**
     * Set the route resolver callback.
     *
     * @return $this
     */
    public function setRouteResolver(Closure $callback) {
        $this->routeResolver = $callback;

        return $this;
    }

    /**
     * Get all of the input and files for the request.
     *
     * @return array
     */
    public function toArray() {
        return $this->all();
    }

    /**
     * Determine if the given offset exists.
     *
     * @param string $offset
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset) {
        $routeParameters = $this->route() ? $this->route()->parameters() : [];

        return carr::has(
            $this->all() + $routeParameters,
            $offset
        );
    }

    /**
     * Get the value at the given offset.
     *
     * @param string $offset
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) {
        return $this->__get($offset);
    }

    /**
     * Set the value at the given offset.
     *
     * @param string $offset
     * @param mixed  $value
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value) {
        $this->getInputSource()->set($offset, $value);
    }

    /**
     * Remove the value at the given offset.
     *
     * @param string $offset
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset) {
        $this->getInputSource()->remove($offset);
    }

    /**
     * Check if an input element is set on the request.
     *
     * @param string $key
     *
     * @return bool
     */
    public function __isset($key) {
        return !is_null($this->__get($key));
    }

    /**
     * Get an input element from the request.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get($key) {
        return carr::get($this->all(), $key, function () use ($key) {
            return $this->route($key);
        });
    }

    /**
     * Get Browser.
     *
     * @return CBrowser
     */
    public function browser() {
        if ($this->browser == null) {
            $this->browser = new CBrowser($this->userAgent());
        }

        return $this->browser;
    }

    /**
     * Validate the request with given rules.
     *
     * @param mixed ...$params
     *
     * @return \CValidation_Validator
     */
    public function validate(array $rules, ...$params) {
        return c::validator()->validate($this->all(), $rules, ...$params);
    }

    /**
     * Validate the request with given rules and error bag.
     *
     * @param mixed ...$params
     * @param mixed $errorBag
     *
     * @return \CValidation_Validator
     */
    public function validateWithBag($errorBag, array $rules, ...$params) {
        try {
            return $this->validate($rules, ...$params);
        } catch (CValidation_Exception $e) {
            $e->errorBag = $errorBag;

            throw $e;
        }
    }

    public function hasValidSignature($absolute = true) {
        return c::url()->hasValidSignature($this, $absolute);
    }

    public function hasValidRelativeSignature() {
        return $this->hasValidSignature(false);
    }

    /**
     * Returns the HTTP referrer, or the default if the referrer is not set.
     *
     * @param mixed $default
     *
     * @return string
     */
    public function referrer($default = false) {
        if (!empty($this->server('HTTP_REFERER'))) {
            // Set referrer
            $ref = $this->server('HTTP_REFERER');

            if (strpos($ref, curl::base(false)) === 0) {
                // Remove the base URL from the referrer
                $ref = substr($ref, strlen(curl::base(false)));
            }
        }

        return isset($ref) ? $ref : $default;
    }

    public function isCresRequest() {
        return $this->hasHeader('X-Cres-Version');
    }

    /**
     * Sets a list of trusted proxies.
     *
     * You should only list the reverse proxies that you manage directly.
     *
     * @param array $proxies          A list of trusted proxies, the string 'REMOTE_ADDR' will be replaced with $_SERVER['REMOTE_ADDR']
     * @param int   $trustedHeaderSet A bit field of Request::HEADER_*, to set which headers to trust from your proxies
     *
     * @return void
     */
    public static function setTrustedProxies(array $proxies, $trustedHeaderSet = null) {
        if ($trustedHeaderSet === null) {
            $trustedHeaderSet = CHTTP_Request::HEADER_X_FORWARDED_FOR | CHTTP_Request::HEADER_X_FORWARDED_HOST | CHTTP_Request::HEADER_X_FORWARDED_PORT | CHTTP_Request::HEADER_X_FORWARDED_PROTO | CHTTP_Request::HEADER_X_FORWARDED_PREFIX | CHTTP_Request::HEADER_X_FORWARDED_AWS_ELB;
        }
        parent::setTrustedProxies($proxies, $trustedHeaderSet);
    }
}
