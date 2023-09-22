<?php

/**
 * Description of UrlGenerator.
 *
 * @author Hery
 */
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class CRouting_UrlGenerator {
    use CTrait_Helper_InteractsWithTime,
        CTrait_Macroable;

    /**
     * The request instance.
     *
     * @var CHTTP_Request
     */
    protected $request;

    /**
     * The forced URL root.
     *
     * @var string
     */
    protected $forcedRoot;

    /**
     * The forced scheme for URLs.
     *
     * @var string
     */
    protected $forceScheme;

    /**
     * A cached copy of the URL root for the current request.
     *
     * @var null|string
     */
    protected $cachedRoot;

    /**
     * A cached copy of the URL scheme for the current request.
     *
     * @var null|string
     */
    protected $cachedScheme;

    /**
     * The root namespace being applied to controller actions.
     *
     * @var string
     */
    protected $rootNamespace;

    /**
     * The session resolver callable.
     *
     * @var callable
     */
    protected $sessionResolver;

    /**
     * The encryption key resolver callable.
     *
     * @var callable
     */
    protected $keyResolver;

    /**
     * The callback to use to format hosts.
     *
     * @var \Closure
     */
    protected $formatHostUsing;

    /**
     * The callback to use to format paths.
     *
     * @var \Closure
     */
    protected $formatPathUsing;

    /**
     * The route URL generator instance.
     *
     * @var null|CRouting_RouteUrlGenerator
     */
    protected $routeGenerator;

    /**
     * @var CRouting_UrlGenerator
     */
    protected static $instance;

    /**
     * @return CRouting_UrlGenerator
     */
    public static function instance() {
        if (static::$instance == null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Create a new URL Generator instance.
     *
     * @return void
     */
    public function __construct() {
        $this->keyResolver = function () {
            return CF::config('app.key');
        };
        $this->sessionResolver = function () {
            return c::session();
        };
        $this->setRequest(CHTTP::request());
    }

    /**
     * Get the full URL for the current request.
     *
     * @return string
     */
    public function full() {
        return $this->request->fullUrl();
    }

    /**
     * Get the current URL for the request.
     *
     * @return string
     */
    public function current() {
        return $this->to($this->request->getPathInfo());
    }

    /**
     * Get the URL for the previous request.
     *
     * @param mixed $fallback
     *
     * @return string
     */
    public function previous($fallback = false) {
        $referrer = $this->request->headers->get('referer');

        $url = $referrer ? $this->to($referrer) : $this->getPreviousUrlFromSession();

        if ($url) {
            return $url;
        } elseif ($fallback) {
            return $this->to($fallback);
        }

        return $this->to('/');
    }

    /**
     * Get the previous URL from the session if possible.
     *
     * @return null|string
     */
    protected function getPreviousUrlFromSession() {
        $session = $this->getSession();

        return $session ? $session->previousUrl() : null;
    }

    /**
     * Generate an absolute URL to the given path.
     *
     * @param string    $path
     * @param mixed     $extra
     * @param null|bool $secure
     *
     * @return string
     */
    public function to($path, $extra = [], $secure = null) {
        // First we will check if the URL is already a valid URL. If it is we will not
        // try to generate a new one but will simply return the URL as is, which is
        // convenient since developers do not always have to check if it's valid.
        if ($this->isValidUrl($path)) {
            return $path;
        }

        $tail = implode(
            '/',
            array_map(
                'rawurlencode',
                (array) $this->formatParameters($extra)
            )
        );

        // Once we have the scheme we will compile the "tail" by collapsing the values
        // into a single string delimited by slashes. This just makes it convenient
        // for passing the array of parameters to this URL as a list of segments.
        $root = $this->formatRoot($this->formatScheme($secure));

        list($path, $query) = $this->extractQueryString($path);

        return $this->format(
            $root,
            '/' . trim($path . '/' . $tail, '/')
        ) . $query;
    }

    /**
     * Generate a secure, absolute URL to the given path.
     *
     * @param string $path
     * @param array  $parameters
     *
     * @return string
     */
    public function secure($path, $parameters = []) {
        return $this->to($path, $parameters, true);
    }

    /**
     * Generate the URL to an application asset.
     *
     * @param string    $path
     * @param null|bool $secure
     *
     * @return string
     */
    public function media($path, $secure = null) {
        if ($this->isValidUrl($path)) {
            return $path;
        }

        $pathOriginal = $path;

        $path = urldecode($path);

        // Once we get the root URL, we will check to see if it contains an index.php
        // file in the paths. If it does, we will remove it since it is not needed
        // for asset paths, but only for routes to endpoints in the application.
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $dirname = pathinfo($path, PATHINFO_DIRNAME);
        $path = $dirname . DS . $filename;
        $root = $this->formatRoot($this->formatScheme($secure));
        $path = CF::findFile('media', $path, false, $extension);
        $count = 1;
        if (cstr::startsWith($path, DOCROOT)) {
            $path = str_replace(DOCROOT, '', $path, $count);
        }

        return $this->removeIndex($root) . '/' . trim($path, '/');
    }

    /**
     * Generate the URL to a secure asset.
     *
     * @param string $path
     *
     * @return string
     */
    public function secureMedia($path) {
        return $this->media($path, true);
    }

    /**
     * Generate the URL to an asset from a custom root domain such as CDN, etc.
     * Alias of assetFrom.
     *
     * @param string    $root
     * @param string    $path
     * @param null|bool $secure
     *
     * @return string
     */
    public function mediaFrom($root, $path, $secure = null) {
        return $this->assetFrom($root, $path, $secure);
    }

    /**
     * Generate the URL to an asset from a custom root domain such as CDN, etc.
     *
     * @param string    $root
     * @param string    $path
     * @param null|bool $secure
     *
     * @return string
     */
    public function assetFrom($root, $path, $secure = null) {
        // Once we get the root URL, we will check to see if it contains an index.php
        // file in the paths. If it does, we will remove it since it is not needed
        // for asset paths, but only for routes to endpoints in the application.
        $root = $this->formatRoot($this->formatScheme($secure), $root);

        return $this->removeIndex($root) . '/' . trim($path, '/');
    }

    /**
     * Remove the index.php file from a path.
     *
     * @param string $root
     *
     * @return string
     */
    protected function removeIndex($root) {
        $i = 'index.php';

        return cstr::contains($root, $i) ? str_replace('/' . $i, '', $root) : $root;
    }

    /**
     * Get the default scheme for a raw URL.
     *
     * @param null|bool $secure
     *
     * @return string
     */
    public function formatScheme($secure = null) {
        if (!is_null($secure)) {
            return $secure ? 'https://' : 'http://';
        }

        if (is_null($this->cachedScheme)) {
            $this->cachedScheme = $this->forceScheme ?: $this->request->getScheme() . '://';
        }

        return $this->cachedScheme;
    }

    /**
     * Create a signed route URL for a named route.
     *
     * @param string                                    $name
     * @param mixed                                     $parameters
     * @param null|\DateTimeInterface|\DateInterval|int $expiration
     * @param bool                                      $absolute
     *
     * @throws \InvalidArgumentException
     *
     * @return string
     */
    public function signedRoute($name, $parameters = [], $expiration = null, $absolute = true) {
        $parameters = carr::wrap($parameters);

        if (array_key_exists('signature', $parameters)) {
            throw new InvalidArgumentException(
                '"Signature" is a reserved parameter when generating signed routes. Please rename your route parameter.'
            );
        }

        if ($expiration) {
            $parameters = $parameters + ['expires' => $this->availableAt($expiration)];
        }

        ksort($parameters);

        $key = call_user_func($this->keyResolver);

        return $this->route($name, $parameters + [
            'signature' => hash_hmac('sha256', $this->route($name, $parameters, $absolute), $key),
        ], $absolute);
    }

    /**
     * Create a temporary signed route URL for a named route.
     *
     * @param string                               $name
     * @param \DateTimeInterface|\DateInterval|int $expiration
     * @param array                                $parameters
     * @param bool                                 $absolute
     *
     * @return string
     */
    public function temporarySignedRoute($name, $expiration, $parameters = [], $absolute = true) {
        return $this->signedRoute($name, $parameters, $expiration, $absolute);
    }

    /**
     * Determine if the given request has a valid signature.
     *
     * @param CHTTP_Request $request
     * @param bool          $absolute
     *
     * @return bool
     */
    public function hasValidSignature(CHTTP_Request $request, $absolute = true) {
        return $this->hasCorrectSignature($request, $absolute) && $this->signatureHasNotExpired($request);
    }

    /**
     * Determine if the given request has a valid signature for a relative URL.
     *
     * @param CHTTP_Request $request
     *
     * @return bool
     */
    public function hasValidRelativeSignature(CHTTP_Request $request) {
        return $this->hasValidSignature($request, false);
    }

    /**
     * Determine if the signature from the given request matches the URL.
     *
     * @param CHTTP_Request $request
     * @param bool          $absolute
     *
     * @return bool
     */
    public function hasCorrectSignature(CHTTP_Request $request, $absolute = true) {
        $url = $absolute ? $request->url() : '/' . $request->path();
        $original = rtrim($url . '?' . carr::query(
            carr::except($request->query(), 'signature')
        ), '?');

        $signature = hash_hmac('sha256', $original, call_user_func($this->keyResolver));

        return hash_equals($signature, (string) $request->query('signature', ''));
    }

    /**
     * Determine if the expires timestamp from the given request is not from the past.
     *
     * @param CHTTP_Request $request
     *
     * @return bool
     */
    public function signatureHasNotExpired(CHTTP_Request $request) {
        $expires = $request->query('expires');

        return !($expires && CCarbon::now()->getTimestamp() > $expires);
    }

    /**
     * Get the URL to a named route.
     *
     * @param string $name
     * @param mixed  $parameters
     * @param bool   $absolute
     *
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     *
     * @return string
     */
    public function route($name, $parameters = [], $absolute = true) {
        if ($this->routes() != null) {
            if (!is_null($route = $this->routes()->getByName($name))) {
                return $this->toRoute($route, $parameters, $absolute);
            }
        }

        throw new RouteNotFoundException("Route [{$name}] not defined.");
    }

    /**
     * Get the URL for a given route instance.
     *
     * @param \CRouting_Route $route
     * @param mixed           $parameters
     * @param bool            $absolute
     *
     * @throws CRouting_Exception_UrlGenerationException
     *
     * @return string
     */
    public function toRoute($route, $parameters, $absolute) {
        $parameters = c::collect(carr::wrap($parameters))->map(function ($value, $key) use ($route) {
            return $value instanceof CRouting_UrlRoutableInterface && $route->bindingFieldFor($key) ? $value->{$route->bindingFieldFor($key)} : $value;
        })->all();

        return $this->routeUrl()->to(
            $route,
            $this->formatParameters($parameters),
            $absolute
        );
    }

    /**
     * Get the URL to a controller action.
     *
     * @param string|array $action
     * @param mixed        $parameters
     * @param bool         $absolute
     *
     * @throws \InvalidArgumentException
     *
     * @return string
     */
    public function action($action, $parameters = [], $absolute = true) {
        if (is_null($route = $this->routes()->getByAction($action = $this->formatAction($action)))) {
            throw new InvalidArgumentException("Action {$action} not defined.");
        }

        return $this->toRoute($route, $parameters, $absolute);
    }

    /**
     * Format the given controller action.
     *
     * @param string|array $action
     *
     * @return string
     */
    protected function formatAction($action) {
        if (is_array($action)) {
            $action = '\\' . implode('@', $action);
        }

        if ($this->rootNamespace && strpos($action, '\\') !== 0) {
            return $this->rootNamespace . '\\' . $action;
        } else {
            return trim($action, '\\');
        }
    }

    /**
     * Format the array of URL parameters.
     *
     * @param mixed|array $parameters
     *
     * @return array
     */
    public function formatParameters($parameters) {
        $parameters = carr::wrap($parameters);

        foreach ($parameters as $key => $parameter) {
            if ($parameter instanceof CRouting_UrlRoutableInterface) {
                $parameters[$key] = $parameter->getRouteKey();
            }
        }

        return $parameters;
    }

    /**
     * Extract the query string from the given path.
     *
     * @param string $path
     *
     * @return array
     */
    protected function extractQueryString($path) {
        if (($queryPosition = strpos($path, '?')) !== false) {
            return [
                substr($path, 0, $queryPosition),
                substr($path, $queryPosition),
            ];
        }

        return [$path, ''];
    }

    /**
     * Get the base URL for the request.
     *
     * @param string      $scheme
     * @param null|string $root
     *
     * @return string
     */
    public function formatRoot($scheme, $root = null) {
        if (is_null($root)) {
            if (is_null($this->cachedRoot)) {
                $this->cachedRoot = $this->forcedRoot ?: $this->request->root();
            }

            $root = $this->cachedRoot;
        }

        $start = cstr::startsWith($root, 'http://') ? 'http://' : 'https://';

        return preg_replace('~' . $start . '~', $scheme, $root, 1);
    }

    /**
     * Format the given URL segments into a single URL.
     *
     * @param string               $root
     * @param string               $path
     * @param null|\CRouting_Route $route
     *
     * @return string
     */
    public function format($root, $path, $route = null) {
        $path = '/' . trim($path, '/');

        if ($this->formatHostUsing) {
            $root = call_user_func($this->formatHostUsing, $root, $route);
        }

        if ($this->formatPathUsing) {
            $path = call_user_func($this->formatPathUsing, $path, $route);
        }

        return trim($root . $path, '/');
    }

    /**
     * Determine if the given path is a valid URL.
     *
     * @param string $path
     *
     * @return bool
     */
    public function isValidUrl($path) {
        if (!preg_match('~^(#|//|https?://|(mailto|tel|sms):)~', $path)) {
            return filter_var($path, FILTER_VALIDATE_URL) !== false;
        }

        return true;
    }

    /**
     * Get the Route URL generator instance.
     *
     * @return \CRouting_RouteUrlGenerator
     */
    protected function routeUrl() {
        if (!$this->routeGenerator) {
            $this->routeGenerator = new CRouting_RouteUrlGenerator($this, $this->request);
        }

        return $this->routeGenerator;
    }

    /**
     * Set the default named parameters used by the URL generator.
     *
     * @param array $defaults
     *
     * @return void
     */
    public function defaults(array $defaults) {
        $this->routeUrl()->defaults($defaults);
    }

    /**
     * Get the default named parameters used by the URL generator.
     *
     * @return array
     */
    public function getDefaultParameters() {
        return $this->routeUrl()->defaultParameters;
    }

    /**
     * Force the scheme for URLs.
     *
     * @param null|string $scheme
     *
     * @return void
     */
    public function forceScheme($scheme) {
        $this->cachedScheme = null;

        $this->forceScheme = $scheme ? $scheme . '://' : null;
    }

    /**
     * Set the forced root URL.
     *
     * @param null|string $root
     *
     * @return void
     */
    public function forceRootUrl($root) {
        $this->forcedRoot = $root ? rtrim($root, '/') : null;

        $this->cachedRoot = null;
    }

    /**
     * Set a callback to be used to format the host of generated URLs.
     *
     * @param \Closure $callback
     *
     * @return $this
     */
    public function formatHostUsing(Closure $callback) {
        $this->formatHostUsing = $callback;

        return $this;
    }

    /**
     * Set a callback to be used to format the path of generated URLs.
     *
     * @param \Closure $callback
     *
     * @return $this
     */
    public function formatPathUsing(Closure $callback) {
        $this->formatPathUsing = $callback;

        return $this;
    }

    /**
     * Get the path formatter being used by the URL generator.
     *
     * @return \Closure
     */
    public function pathFormatter() {
        return $this->formatPathUsing ?: function ($path) {
            return $path;
        };
    }

    /**
     * Get the request instance.
     *
     * @return CHTTP_Request
     */
    public function getRequest() {
        return $this->request;
    }

    /**
     * Set the current request instance.
     *
     * @param CHTTP_Request $request
     *
     * @return void
     */
    public function setRequest(CHTTP_Request $request) {
        $this->request = $request;

        $this->cachedRoot = null;
        $this->cachedScheme = null;

        c::tap(c::optional($this->routeGenerator)->defaultParameters ?: [], function ($defaults) {
            $this->routeGenerator = null;

            if (!empty($defaults)) {
                $this->defaults($defaults);
            }
        });
    }

    /**
     * Get the session implementation from the resolver.
     *
     * @return null|\CSession_Store
     */
    protected function getSession() {
        if ($this->sessionResolver) {
            return call_user_func($this->sessionResolver);
        }
    }

    /**
     * Set the session resolver for the generator.
     *
     * @param callable $sessionResolver
     *
     * @return $this
     */
    public function setSessionResolver(callable $sessionResolver) {
        $this->sessionResolver = $sessionResolver;

        return $this;
    }

    /**
     * Set the encryption key resolver.
     *
     * @param callable $keyResolver
     *
     * @return $this
     */
    public function setKeyResolver(callable $keyResolver) {
        $this->keyResolver = $keyResolver;

        return $this;
    }

    /**
     * Set the root controller namespace.
     *
     * @param string $rootNamespace
     *
     * @return $this
     */
    public function setRootControllerNamespace($rootNamespace) {
        $this->rootNamespace = $rootNamespace;

        return $this;
    }

    /**
     * @return CRouting_RouteCollectionInterface
     */
    public function routes() {
        return CRouting::router()->getRoutes();
    }
}
