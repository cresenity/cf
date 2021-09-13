<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * Common helper class.
 */
use Symfony\Component\PropertyAccess\Exception\NoSuchIndexException;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Symfony\Component\VarDumper\VarDumper;

//@codingStandardsIgnoreStart
class c {
    //@codingStandardsIgnoreEnd

    /**
     * @param string $str
     *
     * @return string
     */
    public static function fixPath($str) {
        $str = str_replace(['/', '\\'], DS, $str);
        return rtrim($str, DS) . DS;
    }

    public static function urShift($a, $b) {
        if ($b == 0) {
            return $a;
        }
        return ($a >> $b) & ~(1 << (8 * PHP_INT_SIZE - 1) >> ($b - 1));
    }

    public static function manimgurl($path) {
        return curl::base() . 'public/manual/' . $path;
    }

    public static function baseIteratee($value) {
        if (\is_callable($value)) {
            return $value;
        }
        if (null === $value) {
            return ['c', 'identity'];
        }
        if (\is_array($value)) {
            return 2 === \count($value) && [0, 1] === \array_keys($value) ? static::baseMatchesProperty($value[0], $value[1]) : static::baseMatches($value);
        }
        return static::property($value);
    }

    public static function baseMatchesProperty($property, $source) {
        return function ($value, $index, $collection) use ($property, $source) {
            $propertyVal = static::property($property);
            return static::isEqual($propertyVal($value, $index, $collection), $source);
        };
    }

    public static function baseMatches($source) {
        return function ($value, $index, $collection) use ($source) {
            if ($value === $source || static::isEqual($value, $source)) {
                return true;
            }
            if (\is_array($source) || $source instanceof \Traversable) {
                foreach ($source as $k => $v) {
                    $propK = c::property($k);
                    if (!static::isEqual($propK($value, $index, $collection), $v)) {
                        return false;
                    }
                }
                return true;
            }
            return false;
        };
    }

    public static function isEqual($value, $other) {
        $factory = CComparator::createFactory();
        $comparator = $factory->getComparatorFor($value, $other);
        try {
            $comparator->assertEquals($value, $other);
            return true;
        } catch (CComparator_Exception_ComparisonFailureException $failure) {
            return false;
        }
    }

    /**
     * Creates a function that returns the value at `path` of a given object.
     *
     * @param array|string $path the path of the property to get
     *
     * @return callable returns the new accessor function
     *
     * @example
     * <code>
     * $objects = [
     *   [ 'a' => [ 'b' => 2 ] ],
     *   [ 'a' => [ 'b' => 1 ] ]
     * ];
     *
     * carr::map($objects, property('a.b'));
     * // => [2, 1]
     *
     * carr::map(sortBy($objects, property(['a', 'b'])), 'a.b');
     * // => [1, 2]
     * </code>
     */
    public static function property($path) {
        $propertyAccess = PropertyAccess::createPropertyAccessorBuilder()
            ->disableExceptionOnInvalidIndex()
            ->getPropertyAccessor();
        return function ($value, $index = 0, $collection = []) use ($path, $propertyAccess) {
            $path = \implode('.', (array) $path);
            if (\is_array($value)) {
                if (false !== \strpos($path, '.')) {
                    $paths = \explode('.', $path);
                    foreach ($paths as $path) {
                        $propPath = static::property($path);
                        $value = $propPath($value, $index, $collection);
                    }
                    return $value;
                }

                if (\is_string($path) && $path[0] !== '[' && $path[strlen($path) - 1] !== ']') {
                    $path = "[$path]";
                }
            }
            try {
                return $propertyAccess->getValue($value, $path);
            } catch (NoSuchPropertyException $e) {
                return null;
            } catch (NoSuchIndexException $e) {
                return null;
            }
        };
    }

    public static function baseGet($object, $path, $defaultValue = null) {
        $path = static::castPath($path, $object);
        $index = 0;
        $length = \count($path);
        while ($object !== null && !is_scalar($object) && $index < $length) {
            $property = static::property(static::toKey($path[$index++]));
            $object = $property($object);
        }
        return ($index > 0 && $index === $length) ? $object : $defaultValue;
    }

    /**
     * Converts `value` to a string key if it's not a string.
     *
     * @param mixed $value the value to inspect
     *
     * @return string returns the key
     */
    public static function toKey($value) {
        if (\is_string($value)) {
            return $value;
        }
        $result = (string) $value;
        return ('0' === $result && (1 / $value) === -INF) ? '-0' : $result;
    }

    public static function castPath($value, $object) {
        if (\is_array($value)) {
            return $value;
        }
        return static::isKey($value, $object) ? [$value] : static::stringToPath((string) $value);
    }

    /**
     * Checks if `value` is a property name and not a property path.
     *
     * @param mixed        $value  the value to check
     * @param object|array $object the object to query keys on
     *
     * @return bool returns `true` if `value` is a property name, else `false`
     */
    public static function isKey($value, $object = []) {
        /* Used to match property names within property paths. */
        $reIsDeepProp = '#\.|\[(?:[^[\]]*|(["\'])(?:(?!\1)[^\\\\]|\\.)*?\1)\]#';
        $reIsPlainProp = '/^\w*$/';
        if (\is_array($value)) {
            return false;
        }
        if (\is_numeric($value)) {
            return true;
        }
        $forceObject = ((object) $object);
        return \preg_match($reIsPlainProp, $value) || !\preg_match($reIsDeepProp, $value) || (null !== $object && isset($forceObject->$value));
    }

    public static function stringToPath(...$args) {
        $memoizeCapped = static::memoizeCapped(function ($string) {
            $reLeadingDot = '/^\./';
            $rePropName = '#[^.[\]]+|\[(?:(-?\d+(?:\.\d+)?)|(["\'])((?:(?!\2)[^\\\\]|\\.)*?)\2)\]|(?=(?:\.|\[\])(?:\.|\[\]|$))#';
            /* Used to match backslashes in property paths. */
            $reEscapeChar = '/\\(\\)?/g';
            $result = [];
            if (\preg_match($reLeadingDot, $string)) {
                $result[] = '';
            }
            \preg_match_all($rePropName, $string, $matches, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($matches as $match) {
                $result[] = isset($match[1]) ? $match[1] : $match[0];
            }
            return $result;
        });
        return $memoizeCapped(...$args);
    }

    public static function memoizeCapped(callable $func) {
        $MaxMemoizeSize = 500;
        $result = static::memoize($func, function ($key) use ($MaxMemoizeSize) {
            if ($this->cache->getSize() === $MaxMemoizeSize) {
                $this->cache->clear();
            }
            return $key;
        });
        return $result;
    }

    /**
     * Creates a function that memoizes the result of `func`. If `resolver` is
     * provided, it determines the cache key for storing the result based on the
     * arguments provided to the memoized function. By default, the first argument
     * provided to the memoized function is used as the map cache key
     *
     * **Note:** The cache is exposed as the `cache` property on the memoized
     * function. Its creation may be customized by replacing the `_.memoize.Cache`
     * constructor with one whose instances implement the
     * [`Map`](http://ecma-international.org/ecma-262/7.0/#sec-properties-of-the-map-prototype-object)
     * method interface of `clear`, `delete`, `get`, `has`, and `set`.
     *
     * @param callable      $func     the function to have its output memoized
     * @param callable|null $resolver the function to resolve the cache key
     *
     * @return callable returns the new memoized function
     *
     * @example
     * <code>
     * $object = ['a' => 1, 'b' => 2];
     * $other = ['c' => 3, 'd' => 4];
     *
     * $values = c::memoize('c::values');
     * $values($object);
     * // => [1, 2]
     *
     * $values($other);
     * // => [3, 4]
     *
     * $object['a'] = 2;
     * $values($object);
     * // => [1, 2]
     *
     * // Modify the result cache.
     * $values->cache->set($object, ['a', 'b']);
     * $values($object);
     * // => ['a', 'b']
     * </code>
     */
    public static function memoize(callable $func, callable $resolver = null) {
        $memoized = CBase::createMemoizeResolver($func, $resolver);
        $memoized->cache = CBase::createMapCache();
        return $memoized;
    }

    public static function assocIndexOf(array $array, $key) {
        $length = \count($array);
        while ($length--) {
            if (static::eq($array[$length][0], $key)) {
                return $length;
            }
        }
        return -1;
    }

    /**
     * Performs a comparison between two values to determine if they are equivalent.
     *
     * @param mixed $value the value to compare
     * @param mixed $other the other value to compare
     *
     * @return bool returns `true` if the values are equivalent, else `false`
     *
     * @example
     * <code>
     * $object = (object) ['a' => 1];
     * $other = (object) ['a' => 1];
     *
     * eq($object, $object);
     * // => true
     *
     * eq($object, $other);
     * // => false
     *
     * eq('a', 'a');
     * // => true
     *
     * eq(['a'], (object) ['a']);
     * // => false
     *
     * eq(INF, INF);
     * // => true
     * </code>
     */
    public static function eq($value, $other) {
        return $value === $other;
    }

    /**
     * Create a collection from the given value.
     *
     * @param mixed $value
     *
     * @return CCollection
     */
    public static function collect($value = null) {
        return new CCollection($value);
    }

    /**
     * Call the given Closure with the given value then return the value.
     *
     * @param mixed         $value
     * @param callable|null $callback
     *
     * @return mixed
     */
    public static function tap($value, $callback = null) {
        if (is_null($callback)) {
            return new CBase_HigherOrderTapProxy($value);
        }

        $callback($value);

        return $value;
    }

    /**
     * Get the class "basename" of the given object / class.
     *
     * @param string|object $class
     *
     * @return string
     */
    public static function classBasename($class) {
        $class = is_object($class) ? get_class($class) : $class;

        $basename = basename(str_replace('\\', '/', $class));
        $basename = carr::last(explode('_', $basename));
        return $basename;
    }

    /**
     * Returns all traits used by a trait and its traits.
     *
     * @param string $trait
     *
     * @return array
     */
    public static function traitUsesRecursive($trait) {
        $traits = class_uses($trait);

        foreach ($traits as $trait) {
            $traits += self::traitUsesRecursive($trait);
        }

        return $traits;
    }

    /**
     * Returns all traits used by a class, its subclasses and trait of their traits.
     *
     * @param object|string $class
     *
     * @return array
     */
    public static function classUsesRecursive($class) {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];

        foreach (array_merge([$class => $class], class_parents($class)) as $class) {
            $results += self::traitUsesRecursive($class);
        }

        return array_unique($results);
    }

    /**
     * Returns true of traits is used by a class, its subclasses and trait of their traits.
     *
     * @param object|string $class
     * @param string        $trait
     *
     * @return array
     */
    public static function hasTrait($class, $trait) {
        return in_array($trait, static::classUsesRecursive($class));
    }

    /**
     * Catch a potential exception and return a default value.
     *
     * @param callable $callback
     * @param mixed    $rescue
     * @param bool     $report
     *
     * @return mixed
     */
    public static function rescue(callable $callback, $rescue = null, $report = true) {
        try {
            return $callback();
        } catch (Throwable $e) {
            if ($report) {
                static::report($e);
            }

            return static::value($rescue);
        }
    }

    /**
     * Return the given value, optionally passed through the given callback.
     *
     * @param mixed         $value
     * @param callable|null $callback
     *
     * @return mixed
     */
    public static function with($value, callable $callback = null) {
        return is_null($callback) ? $value : $callback($value);
    }

    /**
     * Report an exception.
     *
     * @param \Throwable $exception
     *
     * @return void
     */
    public static function report($exception) {
        //@codingStandardsIgnoreStart
        if ($exception instanceof Throwable
            && !$exception instanceof Exception
        ) {
            $exception = new FatalThrowableError($exception);
        }
        //@codingStandardsIgnoreEnd

        $exceptionHandler = CException::exceptionHandler();
        $exceptionHandler->report($exception);
    }

    /**
     * Return the default value of the given value.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public static function value($value) {
        return $value instanceof Closure ? $value() : $value;
    }

    //@codingStandardsIgnoreStart

    /**
     * Dispatch an event and call the listeners.
     *
     * @param string|object $event
     * @param mixed         $payload
     * @param bool          $halt
     *
     * @return array|null
     */
    public static function event(...$args) {
        return CEvent::dispatch(...$args);
    }

    //@codingStandardsIgnoreEnd

    /**
     * Create a new Carbon instance for the current time.
     *
     * @param \DateTimeZone|string|null $tz
     *
     * @return CCarbon
     */
    public static function now($tz = null) {
        return CCarbon::now($tz);
    }

    public static function hrtime($getAsNumber = false) {
        if (function_exists('hrtime')) {
            return hrtime($getAsNumber);
        }

        if ($getAsNumber) {
            return microtime(true) * 1e+6;
        }
        $mt = microtime();
        $s = floor($mt);
        return [$s, ($mt - $s) * 1e+6];
    }

    public static function html($str) {
        return chtml::specialchars($str);
    }

    public static function dirname($path, $count = 1) {
        if ($count > 1) {
            return dirname(static::dirname($path, --$count));
        } else {
            return dirname($path);
        }
    }

    /**
     * Provide access to optional objects.
     *
     * @param mixed         $value
     * @param callable|null $callback
     *
     * @return mixed
     */
    public static function optional($value = null, callable $callback = null) {
        if (is_null($callback)) {
            return new COptional($value);
        } elseif (!is_null($value)) {
            return $callback($value);
        }
    }

    /**
     * Encode HTML special characters in a string.
     *
     * @param CBase_DeferringDisplayableValue|CInterface_Htmlable|string $value
     * @param bool                                                       $doubleEncode
     *
     * @return string
     */
    public static function e($value, $doubleEncode = true) {
        if ($value instanceof CBase_DeferringDisplayableValueInterface) {
            $value = $value->resolveDisplayableValue();
        }

        if ($value instanceof CInterface_Htmlable) {
            return $value->toHtml();
        }

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8', $doubleEncode);
    }

    /**
     * @param string $string
     *
     * @return \cstr|CBase_String
     */
    public static function str($string = null) {
        if (is_null($string)) {
            return new CBase_ForwarderStaticClass(cstr::class);
        }

        return cstr::of($string);
    }

    /**
     * Throw the given exception unless the given condition is true.
     *
     * @param mixed             $condition
     * @param \Throwable|string $exception
     * @param array             ...$parameters
     *
     * @return mixed
     *
     * @throws \Throwable
     */
    public static function throwUnless($condition, $exception, ...$parameters) {
        if (!$condition) {
            throw (is_string($exception) ? new $exception(...$parameters) : $exception);
        }

        return $condition;
    }

    /**
     * Throw the given exception if the given condition is true.
     *
     * @param mixed             $condition
     * @param \Throwable|string $exception
     * @param array             ...$parameters
     *
     * @return mixed
     *
     * @throws \Throwable
     */
    public static function throwIf($condition, $exception, ...$parameters) {
        if ($condition) {
            throw (is_string($exception) ? new $exception(...$parameters) : $exception);
        }

        return $condition;
    }

    /**
     * Gets the value of an environment variable.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public static function env($key, $default = null) {
        return CEnv::get($key, $default);
    }

    /**
     * Translate the given message.
     *
     * @param string|null $key
     * @param array       $replace
     * @param string|null $locale
     *
     * @return CTranslation_Translator|string|array|null
     */
    public static function trans($key = null, $replace = [], $locale = null) {
        return CF::lang($key, $replace, $locale);
    }

    //@codingStandardsIgnoreStart

    /**
     * Translate the given message.
     *
     * @param string|null $key
     * @param array       $replace
     * @param string|null $locale
     *
     * @return string|array|null
     */
    public static function __($key = null, $replace = [], $locale = null) {
        if (is_null($key)) {
            return $key;
        }

        return static::trans($key, $replace, $locale);
    }

    //@codingStandardsIgnoreEnd

    /**
     * @return CSession
     */
    public static function session() {
        return CSession::instance();
    }

    /**
     * Generate a url for the application.
     *
     * @param string|null $path
     * @param mixed       $parameters
     * @param bool|null   $secure
     *
     * @return CRouting_UrlGenerator|string
     */
    public static function url($path = null, $parameters = [], $secure = null) {
        if (is_null($path)) {
            return CRouting::urlGenerator();
        }

        return CRouting::urlGenerator()->to($path, $parameters, $secure);
    }

    /**
     * @return CStorage
     */
    public static function storage() {
        return CStorage::instance();
    }

    /**
     * Create a new Validator instance.
     *
     * @param array $data
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     *
     * @return CValidation_Validator|CValidation_Factory
     */
    public static function validator(array $data = [], array $rules = [], array $messages = [], array $customAttributes = []) {
        $factory = CValidation::factory();

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make($data, $rules, $messages, $customAttributes);
    }

    /**
     * Get the evaluated view contents for the given view.
     *
     * @param string|null                $view
     * @param CInterface_Arrayable|array $data
     * @param array                      $mergeData
     *
     * @return CView_View|CView_Factory
     */
    public static function view($view = null, $data = [], $mergeData = []) {
        $factory = CView::factory();

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make($view, $data, $mergeData);
    }

    /**
     * Throw an HttpException with the given data unless the given condition is true.
     *
     * @param bool                                       $boolean
     * @param CHTTP_Response|\CInterface_Responsable|int $code
     * @param string                                     $message
     * @param array                                      $headers
     *
     * @return void
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public static function abortUnless($boolean, $code, $message = '', array $headers = []) {
        if (!$boolean) {
            static::abort($code, $message, $headers);
        }
    }

    /**
     * Displays a 404 page.
     *
     * @param string $page     URI of page
     * @param string $template custom template
     *
     * @return void
     */
    public static function show404($page = false, $template = false) {
        return static::abort(404);
    }

    public static function abort($code, $message = '', array $headers = []) {
        if ($code instanceof CHTTP_Response) {
            throw new CHttp_Exception_ResponseException($code);
        } elseif ($code instanceof CInterface_Responsable) {
            throw new CHttp_Exception_ResponseException($code->toResponse(CHTTP::request()));
        }

        if ($code == 404) {
            throw new CHTTP_Exception_NotFoundHttpException($message);
        }

        throw new CHTTP_Exception_HttpException($code, $message, null, $headers);
    }

    /**
     * Throw an HttpException with the given data if the given condition is true.
     *
     * @param bool                                       $boolean
     * @param CHTTP_Response|\CInterface_Responsable|int $code
     * @param string                                     $message
     * @param array                                      $headers
     *
     * @return void
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public static function abortIf($boolean, $code, $message = '', array $headers = []) {
        if ($boolean) {
            static::abort($code, $message, $headers);
        }
    }

    /**
     * Get an instance of the current request or an input item from the request.
     *
     * @param array|string|null $key
     * @param mixed             $default
     *
     * @return CHTTP_Request|string|array
     */
    public static function request($key = null, $default = null) {
        if (is_null($key)) {
            return CHTTP::request();
        }

        if (is_array($key)) {
            return CHTTP::request()->only($key);
        }

        $value = CHTTP::request()->__get($key);

        return is_null($value) ? c::value($default) : $value;
    }

    /**
     * Return a new response from the application.
     *
     * @param CView|string|array|null $content
     * @param int                     $status
     * @param array                   $headers
     *
     * @return CHTTP_Response|CHTTP_ResponseFactory
     */
    public static function response($content = '', $status = 200, array $headers = []) {
        $factory = CHTTP::responseFactory();

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make($content, $status, $headers);
    }

    /**
     * Determine if the given value is "blank".
     *
     * @param mixed $value
     *
     * @return bool
     */
    public static function blank($value) {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_numeric($value) || is_bool($value)) {
            return false;
        }

        if ($value instanceof Countable) {
            return count($value) === 0;
        }

        return empty($value);
    }

    /**
     * Determine if a value is "filled".
     *
     * @param mixed $value
     *
     * @return bool
     */
    public static function filled($value) {
        return !static::blank($value);
    }

    /**
     * Get an instance of the redirector.
     *
     * @param string|null $to
     * @param int         $status
     * @param array       $headers
     * @param bool|null   $secure
     *
     * @return CHTTP_Redirector|CHttp_RedirectResponse
     */
    public static function redirect($to = null, $status = 302, $headers = [], $secure = null) {
        if (is_null($to)) {
            return CHTTP::redirector();
        }

        return CHTTP::redirector()->to($to, $status, $headers, $secure);
    }

    /**
     * Get hash manager instance
     *
     * @param null|string $hasher
     *
     * @return CCrypt_HashManager
     */
    public static function hash($hasher = null) {
        return CCrypt_HashManager::instance($hasher);
    }

    /**
     * Get router instance
     *
     * @return CRouting_Router
     */
    public static function router() {
        return CRouting_Router::instance();
    }

    /**
     * Generate the URL to a named route.
     *
     * @param array|string $name
     * @param mixed        $parameters
     * @param bool         $absolute
     *
     * @return string
     */
    public static function route($name, $parameters = [], $absolute = true) {
        return static::url()->route($name, $parameters, $absolute);
    }

    /**
     * Encrypt the given value.
     *
     * @param mixed $value
     * @param bool  $serialize
     *
     * @return string
     */
    public static function encrypt($value, $serialize = true) {
        return CCrypt::encrypter()->encrypt($value, $serialize);
    }

    /**
     * Decrypt the given value.
     *
     * @param string $value
     * @param bool   $unserialize
     *
     * @return mixed
     */
    public static function decrypt($value, $unserialize = true) {
        return CCrypt::encrypter()->decrypt($value, $unserialize);
    }

    /**
     * Dump variable
     *
     * @param mixed $var
     *
     * @return void
     */
    public static function dump($var) {
        foreach (func_get_args() as $var) {
            VarDumper::dump($var);
        }
    }

    /**
     * Retry an operation a given number of times.
     *
     * @param int           $times
     * @param callable      $callback
     * @param int           $sleep
     * @param callable|null $when
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public static function retry($times, $callback, $sleep = 0, $when = null) {
        $attempts = 0;

        beginning:
        $attempts++;
        $times--;

        try {
            return $callback($attempts);
        } catch (Exception $e) {
            if ($times < 1 || ($when && !$when($e))) {
                throw $e;
            }

            if ($sleep) {
                usleep($sleep * 1000);
            }

            goto beginning;
        }
    }

    /**
     * Generate an media path for the application.
     *
     * @param string    $path
     * @param bool|null $secure
     *
     * @return string
     */
    public static function media($path = '', $secure = null) {
        return c::url()->asset($path, $secure);
    }

    /**
     * Retrieve an old input item.
     *
     * @param string|null $key
     * @param mixed       $default
     *
     * @return mixed
     */
    public static function old($key = null, $default = null) {
        return CHTTP::request()->old($key, $default);
    }

    /**
     * Get the available auth instance.
     *
     * @param string|null $guard
     *
     * @return CAuth_Manager|CAuth_GuardInterface|CAuth_StatefulGuardInterface
     */
    public static function auth($guard = null) {
        if (is_null($guard)) {
            return CAuth::manager();
        }

        return CAuth::manager()->guard($guard);
    }

    /**
     * Generate a CSRF token form field.
     *
     * @return CBase_HtmlString
     */
    public static function csrfField() {
        return new CBase_HtmlString('<input type="hidden" name="_token" value="' . static::csrfToken() . '">');
    }

    /**
     * Get the CSRF token value.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    public static function csrfToken() {
        $session = CSession::instance();

        if (isset($session)) {
            return $session->token();
        }

        throw new RuntimeException('Application session store not set.');
    }

    /**
     * Get the available container instance.
     *
     * @param string|null $abstract
     * @param array       $parameters
     *
     * @return mixed|\CContainer_Container
     */
    public static function container($abstract = null, array $parameters = []) {
        if (is_null($abstract)) {
            return CContainer::getInstance();
        }

        return CContainer::getInstance()->make($abstract, $parameters);
    }

    /**
     * Get the CApp instance.
     *
     * @return \CApp
     */
    public static function app() {
        return CApp::instance();
    }

    /**
     * Get the CDatabase instance.
     *
     * @return \CDatabase
     */
    public static function db() {
        return CDatabase::instance();
    }

    public static function userAgent() {
        return (!empty($_SERVER['HTTP_USER_AGENT']) ? trim($_SERVER['HTTP_USER_AGENT']) : '');
    }

    /**
     * Get an item from an array or object using "dot" notation.
     *
     * @param mixed                 $target
     * @param string|array|int|null $key
     * @param mixed                 $default
     *
     * @return mixed
     */
    public static function get($target, $key, $default = null) {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        foreach ($key as $i => $segment) {
            unset($key[$i]);

            if (is_null($segment)) {
                return $target;
            }

            if ($segment === '*') {
                if ($target instanceof CCollection) {
                    $target = $target->all();
                } elseif (!is_array($target)) {
                    return c::value($default);
                }

                $result = [];

                foreach ($target as $item) {
                    $result[] = static::get($item, $key);
                }

                return in_array('*', $key) ? carr::collapse($result) : $result;
            }

            if (carr::accessible($target) && carr::exists($target, $segment)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return static::value($default);
            }
        }

        return $target;
    }

    /**
     * Set an item on an array or object using dot notation.
     *
     * @param mixed        $target
     * @param string|array $key
     * @param mixed        $value
     * @param bool         $overwrite
     *
     * @return mixed
     */
    public static function set(&$target, $key, $value, $overwrite = true) {
        $segments = is_array($key) ? $key : explode('.', $key);

        if (($segment = array_shift($segments)) === '*') {
            if (!carr::accessible($target)) {
                $target = [];
            }

            if ($segments) {
                foreach ($target as &$inner) {
                    static::set($inner, $segments, $value, $overwrite);
                }
            } elseif ($overwrite) {
                foreach ($target as &$inner) {
                    $inner = $value;
                }
            }
        } elseif (carr::accessible($target)) {
            if ($segments) {
                if (!carr::exists($target, $segment)) {
                    $target[$segment] = [];
                }

                static::set($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite || !carr::exists($target, $segment)) {
                $target[$segment] = $value;
            }
        } elseif (is_object($target)) {
            if ($segments) {
                if (!isset($target->{$segment})) {
                    $target->{$segment} = [];
                }

                static::set($target->{$segment}, $segments, $value, $overwrite);
            } elseif ($overwrite || !isset($target->{$segment})) {
                $target->{$segment} = $value;
            }
        } else {
            $target = [];

            if ($segments) {
                static::set($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite) {
                $target[$segment] = $value;
            }
        }

        return $target;
    }

    /**
     * Get the first element of an array. Useful for method chaining.
     *
     * @param array $array
     *
     * @return mixed
     */
    public static function head($array) {
        return reset($array);
    }

    /**
     * Get the last element from an array.
     *
     * @param array $array
     *
     * @return mixed
     */
    public static function last($array) {
        return end($array);
    }

    /**
     * Spaceship operator for php 5.6
     * 0 if $a == $b
     * -1 if $a < $b
     * 1 if $a > $b
     *
     * @param mixed $a
     * @param mixed $b
     *
     * @return void
     */
    public static function spaceshipOperator($a, $b) {
        if ($a == $b) {
            return 0;
        }
        return $a > $b ? 1 : -1;
    }

    public static function dispatch($job) {
        return $job instanceof Closure
            ? new CQueue_PendingClosureDispatch(CQueue_CallQueuedClosure::create($job))
            : new CQueue_PendingDispatch($job);
    }

    /**
     * Determine whether the current environment is Windows based.
     *
     * @return bool
     */
    public static function windowsOs() {
        return PHP_OS_FAMILY === 'Windows';
    }

    /**
     * Transform the given value if it is present.
     *
     * @param mixed    $value
     * @param callable $callback
     * @param mixed    $default
     *
     * @return mixed|null
     */
    public static function transform($value, $callback, $default = null) {
        if (c::filled($value)) {
            return $callback($value);
        }

        if (is_callable($default)) {
            return $default($value);
        }

        return $default;
    }

    /**
     * Replace a given pattern with each value in the array in sequentially.
     *
     * @param string $pattern
     * @param array  $replacements
     * @param string $subject
     *
     * @return string
     */
    public static function pregReplaceArray($pattern, array $replacements, $subject) {
        return preg_replace_callback($pattern, function () use (&$replacements) {
            foreach ($replacements as $key => $value) {
                return array_shift($replacements);
            }
        }, $subject);
    }

    /**
     * @param string $string
     *
     * @return string
     */
    public static function trailingslashit($string) {
        return c::untrailingslashit($string) . '/';
    }

    /**
     *
     * @param string $string
     *
     * @return string
     */
    public static function untrailingslashit($string) {
        return rtrim($string, '/');
    }


}

// End c
