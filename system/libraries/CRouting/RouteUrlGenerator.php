<?php

class CRouting_RouteUrlGenerator {
    /**
     * The named parameter defaults.
     *
     * @var array
     */
    public $defaultParameters = [];

    /**
     * Characters that should not be URL encoded.
     *
     * @var array
     */
    public $dontEncode = [
        '%2F' => '/',
        '%40' => '@',
        '%3A' => ':',
        '%3B' => ';',
        '%2C' => ',',
        '%3D' => '=',
        '%2B' => '+',
        '%21' => '!',
        '%2A' => '*',
        '%7C' => '|',
        '%3F' => '?',
        '%26' => '&',
        '%23' => '#',
        '%25' => '%',
    ];

    /**
     * The URL generator instance.
     *
     * @var CRouting_UrlGenerator
     */
    protected $url;

    /**
     * The request instance.
     *
     * @var CHTTP_Request
     */
    protected $request;

    /**
     * Create a new Route URL generator.
     *
     * @param CRouting_UrlGenerator $url
     * @param CHTTP_Request         $request
     *
     * @return void
     */
    public function __construct($url, $request) {
        $this->url = $url;
        $this->request = $request;
    }

    /**
     * Generate a URL for the given route.
     *
     * @param CRouting_Route $route
     * @param array          $parameters
     * @param bool           $absolute
     *
     * @throws CRouting_Exception_UrlGenerationException
     *
     * @return string
     */
    public function to($route, $parameters = [], $absolute = false) {
        $parameters = $this->formatParameters($route, $parameters);

        $domain = $this->getRouteDomain($route, $parameters);

        // First we will construct the entire URI including the root and query string. Once it
        // has been constructed, we'll make sure we don't have any missing parameters or we
        // will need to throw the exception to let the developers know one was not given.
        $uri = $this->addQueryString($this->url->format(
            $root = $this->replaceRootParameters($route, $domain, $parameters),
            $this->replaceRouteParameters($route->uri(), $parameters),
            $route
        ), $parameters);

        if (preg_match_all('/{(.*?)}/', $uri, $matchedMissingParameters)) {
            throw CRouting_Exception_UrlGenerationException::forMissingParameters($route, $matchedMissingParameters[1]);
        }

        // Once we have ensured that there are no missing parameters in the URI we will encode
        // the URI and prepare it for returning to the developer. If the URI is supposed to
        // be absolute, we will return it as-is. Otherwise we will remove the URL's root.
        $uri = strtr(rawurlencode($uri), $this->dontEncode);

        if (!$absolute) {
            $uri = preg_replace('#^(//|[^/?])+#', '', $uri);

            if ($base = $this->request->getBaseUrl()) {
                $uri = preg_replace('#^' . $base . '#i', '', $uri);
            }

            return '/' . ltrim($uri, '/');
        }

        return $uri;
    }

    /**
     * Get the formatted domain for a given route.
     *
     * @param CRouting_Route $route
     * @param array          $parameters
     *
     * @return string
     */
    protected function getRouteDomain($route, &$parameters) {
        return $route->getDomain() ? $this->formatDomain($route, $parameters) : null;
    }

    /**
     * Format the domain and port for the route and request.
     *
     * @param CRouting_Route $route
     * @param array          $parameters
     *
     * @return string
     */
    protected function formatDomain($route, &$parameters) {
        return $this->addPortToDomain(
            $this->getRouteScheme($route) . $route->getDomain()
        );
    }

    /**
     * Get the scheme for the given route.
     *
     * @param CRouting_Route $route
     *
     * @return string
     */
    protected function getRouteScheme($route) {
        if ($route->httpOnly()) {
            return 'http://';
        } elseif ($route->httpsOnly()) {
            return 'https://';
        }

        return $this->url->formatScheme();
    }

    /**
     * Add the port to the domain if necessary.
     *
     * @param string $domain
     *
     * @return string
     */
    protected function addPortToDomain($domain) {
        $secure = $this->request->isSecure();

        $port = (int) $this->request->getPort();

        return ($secure && $port === 443) || (!$secure && $port === 80)
                    ? $domain : $domain . ':' . $port;
    }

    /**
     * Format the route parameters: named ones by name, positional ones onto the route parameters
     * that still lack a value, routables through their binding field.
     *
     * @param CRouting_Route $route
     * @param mixed          $parameters
     *
     * @return array
     */
    protected function formatParameters(CRouting_Route $route, $parameters) {
        $parameters = carr::wrap($parameters);

        $namedParameters = [];
        $namedQueryParameters = [];
        $requiredRouteParametersWithoutDefaultsOrNamedParameters = [];

        $routeParameters = $route->parameterNames();
        $optionalParameters = $route->getOptionalParameterNames();

        foreach ($routeParameters as $name) {
            if (isset($parameters[$name])) {
                $namedParameters[$name] = $parameters[$name];
                unset($parameters[$name]);

                continue;
            } else {
                $bindingField = $route->bindingFieldFor($name);
                $defaultParameterKey = $bindingField ? "$name:$bindingField" : $name;

                if (!isset($this->defaultParameters[$defaultParameterKey]) && !isset($optionalParameters[$name])) {
                    array_push($requiredRouteParametersWithoutDefaultsOrNamedParameters, $name);
                }
            }

            $namedParameters[$name] = '';
        }

        // Named parameters that don't have route parameters will be used for query string...
        foreach ($parameters as $key => $value) {
            if (is_string($key)) {
                $namedQueryParameters[$key] = $value;

                unset($parameters[$key]);
            }
        }

        // Match positional parameters to the route parameters that didn't have a value in order...
        if (count($parameters) == count($requiredRouteParametersWithoutDefaultsOrNamedParameters)) {
            foreach (array_reverse($requiredRouteParametersWithoutDefaultsOrNamedParameters) as $name) {
                if (count($parameters) === 0) {
                    break;
                }

                $namedParameters[$name] = array_pop($parameters);
            }
        }

        $offset = 0;
        $emptyParameters = array_filter($namedParameters, function ($val) {
            return $val === '';
        });

        if ($requiredRouteParametersWithoutDefaultsOrNamedParameters !== [] && count($parameters) !== count($emptyParameters)) {
            // Find the index of the first required parameter...
            $offset = array_search($requiredRouteParametersWithoutDefaultsOrNamedParameters[0], array_keys($namedParameters));

            // If more empty parameters remain, adjust the offset...
            $remaining = count($emptyParameters) - $offset - count($parameters);

            if ($remaining < 0) {
                $offset += $remaining;
            }

            if ($offset < 0) {
                $offset = 0;
            }
        } elseif ($requiredRouteParametersWithoutDefaultsOrNamedParameters === [] && count($parameters) !== 0) {
            // All passed parameters are for parameters that have default values...
            $remainingCount = count($parameters);

            for ($i = count($namedParameters) - 1; $i >= 0; $i--) {
                if ($namedParameters[array_keys($namedParameters)[$i]] === '') {
                    $offset = $i;
                    $remainingCount--;

                    if ($remainingCount === 0) {
                        break;
                    }
                }
            }
        }

        // Starting from the offset, match any passed parameters from left to right...
        for ($i = $offset; $i < count($namedParameters); $i++) {
            $key = array_keys($namedParameters)[$i];

            if ($namedParameters[$key] !== '') {
                continue;
            } elseif (!empty($parameters)) {
                $namedParameters[$key] = array_shift($parameters);
            }
        }

        // Fill leftmost parameters with defaults if the loop above was offset...
        foreach ($namedParameters as $key => $value) {
            $bindingField = $route->bindingFieldFor($key);
            $defaultParameterKey = $bindingField ? "$key:$bindingField" : $key;

            if ($value === '' && isset($this->defaultParameters[$defaultParameterKey])) {
                $namedParameters[$key] = $this->defaultParameters[$defaultParameterKey];
            }
        }

        // Any remaining values in $parameters are unnamed query string parameters...
        $parameters = array_merge($namedParameters, $namedQueryParameters, $parameters);

        foreach ($parameters as $key => $value) {
            if ($value instanceof CRouting_UrlRoutableInterface && $route->bindingFieldFor($key)) {
                $parameters[$key] = $value->{$route->bindingFieldFor($key)};
            }
        }

        return $this->url->formatParameters($parameters);
    }

    /**
     * Encode a parameter value that is being substituted into a route URI.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    protected function encodeParameter($value) {
        if ($value instanceof CRouting_EncodedParameter) {
            return $value->value();
        }

        return is_string($value) || (is_object($value) && method_exists($value, '__toString'))
            ? strtr((string) $value, ['%' => '%25', '?' => '%3F', '#' => '%23'])
            : $value;
    }

    /**
     * Replace the parameters on the root path.
     *
     * @param CRouting_Route $route
     * @param string         $domain
     * @param array          $parameters
     *
     * @return string
     */
    protected function replaceRootParameters($route, $domain, &$parameters) {
        $scheme = $this->getRouteScheme($route);

        return $this->replaceRouteParameters(
            $this->url->formatRoot($scheme, $domain),
            $parameters
        );
    }

    /**
     * Replace all of the wildcard parameters for a route path.
     *
     * @param string $path
     * @param array  $parameters
     *
     * @return string
     */
    protected function replaceRouteParameters($path, array &$parameters) {
        $path = $this->replaceNamedParameters($path, $parameters);

        $path = preg_replace_callback('/\{.*?\}/', function ($match) use (&$parameters) {
            // Reset only the numeric keys...
            $parameters = array_merge($parameters);

            return (!isset($parameters[0]) && !cstr::endsWith($match[0], '?}'))
                        ? $match[0]
                        : $this->encodeParameter(carr::pull($parameters, 0));
        }, $path);

        return trim(preg_replace('/\{.*?\?\}/', '', $path), '/');
    }

    /**
     * Replace all of the named parameters in the path.
     *
     * @param string $path
     * @param array  $parameters
     *
     * @return string
     */
    protected function replaceNamedParameters($path, &$parameters) {
        return preg_replace_callback('/\{(.*?)(\?)?\}/', function ($m) use (&$parameters) {
            if (isset($parameters[$m[1]]) && $parameters[$m[1]] !== '') {
                return $this->encodeParameter(carr::pull($parameters, $m[1]));
            } elseif (isset($this->defaultParameters[$m[1]])) {
                return $this->encodeParameter($this->defaultParameters[$m[1]]);
            } elseif (isset($parameters[$m[1]])) {
                carr::pull($parameters, $m[1]);
            }

            return $m[0];
        }, $path);
    }

    /**
     * Add a query string to the URI.
     *
     * @param string $uri
     * @param array  $parameters
     *
     * @return mixed|string
     */
    protected function addQueryString($uri, array $parameters) {
        // If the URI has a fragment we will move it to the end of this URI since it will
        // need to come after any query string that may be added to the URL else it is
        // not going to be available. We will remove it then append it back on here.
        if (!is_null($fragment = parse_url($uri, PHP_URL_FRAGMENT))) {
            $uri = preg_replace('/#.*/', '', $uri);
        }

        $uri .= $this->getRouteQueryString($parameters);

        return is_null($fragment) ? $uri : $uri . "#{$fragment}";
    }

    /**
     * Get the query string for a given route.
     *
     * @param array $parameters
     *
     * @return string
     */
    protected function getRouteQueryString(array $parameters) {
        // First we will get all of the string parameters that are remaining after we
        // have replaced the route wildcards. We'll then build a query string from
        // these string parameters then use it as a starting point for the rest.
        if (count($parameters) === 0) {
            return '';
        }

        $query = carr::query(
            $keyed = $this->getStringParameters($parameters)
        );

        // Lastly, if there are still parameters remaining, we will fetch the numeric
        // parameters that are in the array and add them to the query string or we
        // will make the initial query string if it wasn't started with strings.
        if (count($keyed) < count($parameters)) {
            $query .= '&' . implode(
                '&',
                $this->getNumericParameters($parameters)
            );
        }

        $query = trim($query, '&');

        return $query === '' ? '' : "?{$query}";
    }

    /**
     * Get the string parameters from a given list.
     *
     * @param array $parameters
     *
     * @return array
     */
    protected function getStringParameters(array $parameters) {
        return array_filter($parameters, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    /**
     * Get the numeric parameters from a given list.
     *
     * @param array $parameters
     *
     * @return array
     */
    protected function getNumericParameters(array $parameters) {
        return array_filter($parameters, 'is_numeric', ARRAY_FILTER_USE_KEY);
    }

    /**
     * Set the default named parameters used by the URL generator.
     *
     * @param array $defaults
     *
     * @return void
     */
    public function defaults(array $defaults) {
        $this->defaultParameters = array_merge(
            $this->defaultParameters,
            $defaults
        );
    }
}
