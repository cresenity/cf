<?php

defined('SYSPATH') or die('No direct access allowed.');

use Illuminate\Contracts\Support\Arrayable;

/**
 * Class carr.
 */
// @codingStandardsIgnoreStart
class carr {
    // @codingStandardsIgnoreEnd

    /**
     * Determine whether the given value is array accessible.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public static function accessible($value) {
        return is_array($value) || $value instanceof ArrayAccess;
    }

    /**
     * Tests if an array is associative or not.
     *
     *     // Returns TRUE
     *     carr::isAssoc(array('username' => 'john.doe'));
     *
     *     // Returns FALSE
     *     carr::isAssoc('foo', 'bar');
     *
     * @param array $array array to check
     *
     * @return bool
     */
    public static function isAssoc(array $array) {
        // Keys of the array
        $keys = array_keys($array);

        // If the array keys of the keys match the keys, then the array must
        // not be associative (e.g. the keys array looked like {0:0, 1:1...}).
        return array_keys($keys) !== $keys;
    }

    /**
     * Determines if an array is a list.
     *
     * An array is a "list" if all array keys are sequential integers starting from 0 with no gaps in between.
     *
     * @param array $array
     *
     * @return bool
     */
    public static function isList($array) {
        return !self::isAssoc($array);
    }

    /**
     * Alias of isAssoc.
     *
     * @param array $array array to check
     *
     * @return bool
     *
     * @deprecated
     */
    // @codingStandardsIgnoreStart
    public static function is_assoc(array $array) {
        return static::isAssoc($array);
    }

    // @codingStandardsIgnoreEnd

    /**
     * Test if a value is an array with an additional check for array-like objects.
     *
     *     // Returns TRUE
     *     carr::is_array(array());
     *     carr::is_array(new ArrayObject);
     *
     *     // Returns FALSE
     *     carr::is_array(FALSE);
     *     carr::is_array('not an array!');
     *     carr::is_array(Database::instance());
     *
     * @param mixed $value value to check
     *
     * @return bool
     */
    public static function isArray($value) {
        if (is_array($value)) {
            // Definitely an array
            return true;
        } else {
            // Possibly a Traversable object, functionally the same as an array
            return is_object($value) and $value instanceof Traversable;
        }
    }

    /**
     * Alias of isArray.
     *
     * @param mixed $value value to check
     *
     * @return bool
     *
     * @deprecated
     */
    // @codingStandardsIgnoreStart
    public static function is_array($value) {
        return static::isArray($value);
    }

    // @codingStandardsIgnoreEnd

    /**
     * Get an item from an array using "dot" notation.
     *
     * @param \ArrayAccess|array $array
     * @param string|int         $key
     * @param mixed              $default
     *
     * @return mixed
     */
    public static function get($array, $key, $default = null) {
        if (!static::accessible($array)) {
            return c::value($default);
        }

        if (is_null($key)) {
            return $array;
        }

        if (static::exists($array, $key)) {
            return $array[$key];
        }

        if (strpos($key, '.') === false) {
            return isset($array[$key]) ? $array[$key] : c::value($default);
        }

        foreach (explode('.', $key) as $segment) {
            if (static::accessible($array) && static::exists($array, $segment)) {
                $array = $array[$segment];
            } else {
                return c::value($default);
            }
        }

        return $array;
    }

    /**
     * Set an array item to a given value using "dot" notation.
     *
     * If no key is given to the method, the entire array will be replaced.
     *
     * @param array  $array
     * @param string $key
     * @param mixed  $value
     *
     * @return array
     */
    public static function set(&$array, $key, $value) {
        if (is_null($key)) {
            return $array = $value;
        }

        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            // If the key doesn't exist at this depth, we will just create an empty array
            // to hold the next value, allowing us to create the arrays to hold final
            // values at the correct depth. Then we'll keep digging into the array.
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }

    /**
     * Gets a value from an array using a dot separated path.
     *
     *     // Get the value of $array['foo']['bar']
     *     $value = carr::path($array, 'foo.bar');
     *
     * Using a wildcard "*" will search intermediate arrays and return an array.
     *
     *     // Get the values of "color" in theme
     *     $colors = carr::path($array, 'theme.*.color');
     *
     *     // Using an array of keys
     *     $colors = carr::path($array, array('theme', '*', 'color'));
     *
     * @param array  $array   array to search
     * @param string $path    key path string (delimiter separated) or array of keys
     * @param mixed  $default default value if the path is not set
     *
     * @return mixed
     *
     * @deprecated since 1.2, use carr::get
     */
    public static function path($array, $path, $default = null) {
        return carr::get($array, $path, $default);
    }

    /**
     * Set a value on an array by path.
     *
     * @param array  $array Array to update
     * @param string $path  Path
     * @param mixed  $value Value to set
     *
     * @see carr::path()
     * @deprecated since 1.2 use set
     */
    //@codingStandardsIgnoreStart
    public static function set_path(&$array, $path, $value) {
        return carr::set($array, $path, $value);
    }

    //@codingStandardsIgnoreEnd

    /**
     * Return a callback array from a string, eg: limit[10,20] would become
     * array('limit', array('10', '20')).
     *
     * @param string $str callback string
     *
     * @return array
     */
    //@codingStandardsIgnoreStart
    public static function callback_string($str) {
        // command[param,param]
        if (preg_match('/([^\[]*+)\[(.+)\]/', (string) $str, $match)) {
            // command
            $command = $match[1];

            // param,param
            $params = preg_split('/(?<!\\\\),/', $match[2]);
            $params = str_replace('\,', ',', $params);
        } else {
            // command
            $command = $str;

            // No params
            $params = null;
        }

        return [$command, $params];
    }

    //@codingStandardsIgnoreEnd

    /**
     * Rotates a 2D array clockwise.
     * Example, turns a 2x3 array into a 3x2 array.
     *
     * @param array $source_array array to rotate
     * @param bool  $keep_keys    keep the keys in the final rotated array.
     *                            the sub arrays of the source array need to have the same key values.
     *                            if your subkeys might not match, you need to pass FALSE here!
     *
     * @return array
     */
    public static function rotate($source_array, $keep_keys = true) {
        $new_array = [];
        foreach ($source_array as $key => $value) {
            $value = ($keep_keys === true) ? $value : array_values($value);
            foreach ($value as $k => $v) {
                $new_array[$k][$key] = $v;
            }
        }

        return $new_array;
    }

    /**
     * Removes a key from an array and returns the value.
     *
     * @param string $key   to return
     * @param array  $array to work on
     *
     * @return mixed value of the requested array key
     */
    public static function remove($key, &$array) {
        if (!array_key_exists($key, $array)) {
            return null;
        }

        $val = $array[$key];
        unset($array[$key]);

        return $val;
    }

    /**
     * Retrieves multiple paths from an array. If the path does not exist in the
     * array, the default value will be added instead.
     *
     *     // Get the values "username", "password" from $_POST
     *     $auth = carr::extract($_POST, array('username', 'password'));
     *
     *     // Get the value "level1.level2a" from $data
     *     $data = array('level1' => array('level2a' => 'value 1', 'level2b' => 'value 2'));
     *     carr::extract($data, array('level1.level2a', 'password'));
     *
     * @param array $array   array to extract paths from
     * @param array $paths   list of path
     * @param mixed $default default value
     *
     * @return array
     */
    public static function extract($array, array $paths, $default = null) {
        $found = [];
        foreach ($paths as $path) {
            carr::set($found, $path, carr::get($array, $path, $default));
        }

        return $found;
    }

    /**
     * Recursively merge two or more arrays. Values in an associative array
     * overwrite previous values with the same key. Values in an indexed array
     * are appended, but only when they do not already exist in the result.
     *
     * Note that this does not work the same as [array_merge_recursive](http://php.net/array_merge_recursive)!
     *
     *     $john = array('name' => 'john', 'children' => array('fred', 'paul', 'sally', 'jane'));
     *     $mary = array('name' => 'mary', 'children' => array('jane'));
     *
     *     // John and Mary are married, merge them together
     *     $john = carr::merge($john, $mary);
     *
     *     // The output of $john will now be:
     *     array('name' => 'mary', 'children' => array('fred', 'paul', 'sally', 'jane'))
     *
     * @param array $array1     initial array
     * @param array $array2,... array to merge
     *
     * @return array
     */
    public static function merge($array1, $array2) {
        if ($array1 instanceof Arrayable) {
            $array1 = $array1->toArray();
        }
        if ($array2 instanceof Arrayable) {
            $array2 = $array2->toArray();
        }
        if (carr::isAssoc($array2)) {
            foreach ($array2 as $key => $value) {
                if (is_array($value)
                    && isset($array1[$key])
                    && is_array($array1[$key])
                ) {
                    $array1[$key] = carr::merge($array1[$key], $value);
                } else {
                    $array1[$key] = $value;
                }
            }
        } else {
            foreach ($array2 as $value) {
                if (!in_array($value, $array1, true)) {
                    $array1[] = $value;
                }
            }
        }

        if (func_num_args() > 2) {
            foreach (array_slice(func_get_args(), 2) as $array2) {
                if (carr::isAssoc($array2)) {
                    foreach ($array2 as $key => $value) {
                        if (is_array($value)
                            && isset($array1[$key])
                            && is_array($array1[$key])
                        ) {
                            $array1[$key] = carr::merge($array1[$key], $value);
                        } else {
                            $array1[$key] = $value;
                        }
                    }
                } else {
                    foreach ($array2 as $value) {
                        if (!in_array($value, $array1, true)) {
                            $array1[] = $value;
                        }
                    }
                }
            }
        }

        return $array1;
    }

    /**
     * Overwrites an array with values from input arrays.
     * Keys that do not exist in the first array will not be added!
     *
     *     $a1 = array('name' => 'john', 'mood' => 'happy', 'food' => 'bacon');
     *     $a2 = array('name' => 'jack', 'food' => 'tacos', 'drink' => 'beer');
     *
     *     // Overwrite the values of $a1 with $a2
     *     $array = carr::overwrite($a1, $a2);
     *
     *     // The output of $array will now be:
     *     array('name' => 'jack', 'mood' => 'happy', 'food' => 'tacos')
     *
     * @param array $array1 master array
     * @param array $array2 input arrays that will overwrite existing values
     *
     * @return array
     */
    public static function overwrite($array1, $array2) {
        foreach (array_intersect_key($array2, $array1) as $key => $value) {
            $array1[$key] = $value;
        }

        if (func_num_args() > 2) {
            foreach (array_slice(func_get_args(), 2) as $array2) {
                foreach (array_intersect_key($array2, $array1) as $key => $value) {
                    $array1[$key] = $value;
                }
            }
        }

        return $array1;
    }

    /**
     * Because PHP does not have this function.
     *
     * @param array  $array to unshift
     * @param string $key   to unshift
     * @param mixed  $val   to unshift
     *
     * @return array
     */
    //@codingStandardsIgnoreStart
    public static function unshift_assoc(array &$array, $key, $val) {
        $array = array_reverse($array, true);
        $array[$key] = $val;
        $array = array_reverse($array, true);

        return $array;
    }

    //@codingStandardsIgnoreEnd

    /**
     * Because PHP does not have this function, and array_walk_recursive creates
     * references in arrays and is not truly recursive.
     *
     * @param mixed $callback to apply to each member of the array
     * @param array $array    to map to
     *
     * @return array
     */
    public static function mapRecursive($callback, array $array) {
        foreach ($array as $key => $val) {
            // Map the callback to the key
            $array[$key] = is_array($val) ? static::mapRecursive($callback, $val) : call_user_func($callback, $val);
        }

        return $array;
    }

    /**
     * Because PHP does not have this function, and array_walk_recursive creates
     * references in arrays and is not truly recursive.
     *
     * @param mixed $callback to apply to each member of the array
     * @param array $array    to map to
     *
     * @return array
     *
     * @deprecated since version 1.1
     */
    //@codingStandardsIgnoreStart
    public static function map_recursive($callback, array $array) {
        return static::mapRecursive($callback, $array);
    }

    //@codingStandardsIgnoreEnd

    /**
     * @param mixed $needle   the value to search for
     * @param array $haystack an array of values to search in
     * @param bool  $sort     sort the array now
     *
     * @return int|false the index of the match or FALSE when not found
     */
    //@codingStandardsIgnoreStart
    public static function binary_search($needle, $haystack, $sort = false) {
        if ($sort) {
            sort($haystack);
        }

        $high = count($haystack) - 1;
        $low = 0;

        while ($low <= $high) {
            $mid = ($low + $high) >> 1;

            if ($haystack[$mid] < $needle) {
                $low = $mid + 1;
            } elseif ($haystack[$mid] > $needle) {
                $high = $mid - 1;
            } else {
                return $mid;
            }
        }

        return false;
    }

    //@codingStandardsIgnoreEnd

    /**
     * Fill an array with a range of numbers.
     *
     * @param int $step step
     * @param int $max  ending number
     *
     * @return array
     */
    public static function range($step = 10, $max = 100) {
        if ($step < 1) {
            return [];
        }

        $array = [];
        for ($i = $step; $i <= $max; $i += $step) {
            $array[$i] = $i;
        }

        return $array;
    }

    /**
     * Recursively convert an array to an object.
     *
     * @param   array   array to convert
     * @param mixed $class
     *
     * @return object
     */
    //@codingStandardsIgnoreStart
    public static function to_object(array $array, $class = 'stdClass') {
        $object = new $class();

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                // Convert the array to an object
                $value = carr::to_object($value, $class);
            }

            // Add the value to the object
            $object->{$key} = $value;
        }

        return $object;
    }

    //@codingStandardsIgnoreEnd

    /**
     * Replace arr.
     *
     * @return void
     */
    public static function replace() {
        $args = func_get_args();
        $num_args = func_num_args();
        $res = [];
        for ($i = 0; $i < $num_args; $i++) {
            if (is_array($args[$i])) {
                foreach ($args[$i] as $key => $val) {
                    $res[$key] = $val;
                }
            } else {
                trigger_error(__FUNCTION__ . '(): Argument #' . ($i + 1) . ' is not an array', E_USER_WARNING);

                return null;
            }
        }

        return $res;
    }

    /**
     * Return the first element in an array passing a given truth test.
     *
     * @param array         $array
     * @param null|callable $callback
     * @param mixed         $default
     *
     * @return mixed
     */
    public static function first($array, ?callable $callback = null, $default = null) {
        if (is_null($callback)) {
            if (empty($array)) {
                return c::value($default);
            }

            foreach ($array as $item) {
                return $item;
            }
        }

        foreach ($array as $key => $value) {
            if (call_user_func($callback, $value, $key)) {
                return $value;
            }
        }

        return c::value($default);
    }

    /**
     * Return the last element in an array passing a given truth test.
     *
     * @param array         $array
     * @param null|callable $callback
     * @param mixed         $default
     *
     * @return mixed
     */
    public static function last($array, ?callable $callback = null, $default = null) {
        if (is_null($callback)) {
            return empty($array) ? c::value($default) : end($array);
        }

        return static::first(array_reverse($array, true), $callback, $default);
    }

    /**
     * Flatten a multi-dimensional array into a single level.
     *
     * @param array $array
     * @param int   $depth
     *
     * @return array
     */
    public static function flatten($array, $depth = INF) {
        $result = [];

        foreach ($array as $item) {
            $item = $item instanceof CCollection ? $item->all() : $item;

            if (!is_array($item)) {
                $result[] = $item;
            } elseif ($depth === 1) {
                $result = array_merge($result, array_values($item));
            } else {
                $result = array_merge($result, static::flatten($item, $depth - 1));
            }
        }

        return $result;
    }

    /**
     * If the given value is not an array, wrap it in one.
     *
     * @param mixed $value
     *
     * @return array
     */
    public static function wrap($value) {
        if (is_null($value)) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    /**
     * Add an element to an array using "dot" notation if it doesn't exist.
     *
     * @param array  $array
     * @param string $key
     * @param mixed  $value
     *
     * @return array
     */
    public static function add($array, $key, $value) {
        if (is_null(static::get($array, $key))) {
            static::set($array, $key, $value);
        }

        return $array;
    }

    /**
     * Conditionally compile classes from an array into a CSS class list.
     *
     * @param array $array
     *
     * @return string
     */
    public static function toCssClasses($array) {
        $classList = static::wrap($array);

        $classes = [];

        foreach ($classList as $class => $constraint) {
            if (is_numeric($class)) {
                $classes[] = $constraint;
            } elseif ($constraint) {
                $classes[] = $class;
            }
        }

        return implode(' ', $classes);
    }

    /**
     * Conditionally compile styles from an array into a style list.
     *
     * @param array $array
     *
     * @return string
     */
    public static function toCssStyles($array) {
        $styleList = static::wrap($array);

        $styles = [];

        foreach ($styleList as $class => $constraint) {
            if (is_numeric($class)) {
                $styles[] = cstr::finish($constraint, ';');
            } elseif ($constraint) {
                $styles[] = cstr::finish($class, ';');
            }
        }

        return implode(' ', $styles);
    }

    /**
     * Filter the array using the given callback.
     *
     * @param array    $array
     * @param callable $callback
     *
     * @return array
     */
    public static function where($array, callable $callback) {
        $new_array = [];
        foreach ($array as $k => $v) {
            $passed = true;
            if ($callback != null) {
                if (!call_user_func($callback, $v, $k)) {
                    $passed = false;
                }
            }

            if ($passed) {
                $new_array[$k] = $v;
            }
        }

        return $new_array;
    }

    /**
     * Filter items where the value is not null.
     *
     * @param array $array
     *
     * @return array
     */
    public static function whereNotNull($array) {
        return static::where($array, function ($value) {
            return !is_null($value);
        });
    }

    /**
     * Get all of the given array except for a specified array of keys.
     *
     * @param array        $array
     * @param array|string $keys
     *
     * @return array
     */
    public static function except($array, $keys) {
        static::forget($array, $keys);

        return $array;
    }

    /**
     * Remove one or many array items from a given array using "dot" notation.
     *
     * @param array        $array
     * @param array|string $keys
     *
     * @return void
     */
    public static function forget(&$array, $keys) {
        $original = &$array;

        $keys = (array) $keys;

        if (count($keys) === 0) {
            return;
        }

        foreach ($keys as $key) {
            // if the exact key exists in the top-level, remove it
            if (static::exists($array, $key)) {
                unset($array[$key]);

                continue;
            }

            $parts = explode('.', $key);

            // clean up before each pass
            $array = &$original;

            while (count($parts) > 1) {
                $part = array_shift($parts);

                if (isset($array[$part]) && is_array($array[$part])) {
                    $array = &$array[$part];
                } else {
                    continue 2;
                }
            }

            unset($array[array_shift($parts)]);
        }
    }

    /**
     * Determine if the given key exists in the provided array.
     *
     * @param \ArrayAccess|array $array
     * @param string|int         $key
     *
     * @return bool
     */
    public static function exists($array, $key) {
        if ($array instanceof CInterface_Enumerable || $array instanceof CCollection) {
            return $array->has($key);
        }
        if ($array instanceof ArrayAccess) {
            return $array->offsetExists($key);
        }

        if (is_float($key)) {
            $key = (string) $key;
        }

        return array_key_exists($key, $array);
    }

    /**
     * Pluck an array of values from an array.
     *
     * @param array             $array
     * @param string|array      $value
     * @param null|string|array $key
     *
     * @return array
     */
    public static function pluck($array, $value, $key = null) {
        $results = [];

        list($value, $key) = static::explodePluckParameters($value, $key);

        foreach ($array as $item) {
            $itemValue = c::get($item, $value);

            // If the key is "null", we will just append the value to the array and keep
            // looping. Otherwise we will key the array using the value of the key we
            // received from the developer. Then we'll return the final array form.
            if (is_null($key)) {
                $results[] = $itemValue;
            } else {
                $itemKey = c::get($item, $key);

                if (is_object($itemKey) && method_exists($itemKey, '__toString')) {
                    $itemKey = (string) $itemKey;
                }

                $results[$itemKey] = $itemValue;
            }
        }

        return $results;
    }

    /**
     * Explode the "value" and "key" arguments passed to "pluck".
     *
     * @param string|array      $value
     * @param null|string|array $key
     *
     * @return array
     */
    protected static function explodePluckParameters($value, $key) {
        $value = is_string($value) ? explode('.', $value) : $value;

        $key = is_null($key) || is_array($key) ? $key : explode('.', $key);

        return [$value, $key];
    }

    /**
     * Collapse an array of arrays into a single array.
     *
     * @param array $array
     *
     * @return array
     */
    public static function collapse($array) {
        $results = [];
        foreach ($array as $values) {
            if ($values instanceof CCollection) {
                $values = $values->all();
            } elseif (!is_array($values)) {
                continue;
            }
            $results = array_merge($results, $values);
        }

        return $results;
    }

    /**
     * Cross join the given arrays, returning all possible permutations.
     *
     * @param iterable ...$arrays
     *
     * @return array
     */
    public static function crossJoin(...$arrays) {
        $results = [[]];

        foreach ($arrays as $index => $array) {
            $append = [];

            foreach ($results as $product) {
                foreach ($array as $item) {
                    $product[$index] = $item;

                    $append[] = $product;
                }
            }

            $results = $append;
        }

        return $results;
    }

    /**
     * Divide an array into two arrays. One with keys and the other with values.
     *
     * @param array $array
     *
     * @return array
     */
    public static function divide($array) {
        return [array_keys($array), array_values($array)];
    }

    /**
     * Check if an item or items exist in an array using "dot" notation.
     *
     * @param \ArrayAccess|array $array
     * @param string|array       $keys
     *
     * @return bool
     */
    public static function has($array, $keys) {
        if (is_null($keys)) {
            return false;
        }

        $keys = (array) $keys;

        if (!$array) {
            return false;
        }

        if ($keys === []) {
            return false;
        }

        foreach ($keys as $key) {
            $subKeyArray = $array;

            if (static::exists($array, $key)) {
                continue;
            }

            foreach (explode('.', $key) as $segment) {
                if (static::accessible($subKeyArray) && static::exists($subKeyArray, $segment)) {
                    $subKeyArray = $subKeyArray[$segment];
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Determine if any of the keys exist in an array using "dot" notation.
     *
     * @param \ArrayAccess|array $array
     * @param string|array       $keys
     *
     * @return bool
     */
    public static function hasAny($array, $keys) {
        if (is_null($keys)) {
            return false;
        }

        $keys = (array) $keys;

        if (!$array) {
            return false;
        }

        if ($keys === []) {
            return false;
        }

        foreach ($keys as $key) {
            if (static::has($array, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Flatten a multi-dimensional associative array with dots.
     *
     * @param array  $array
     * @param string $prepend
     *
     * @return array
     */
    public static function dot($array, $prepend = '') {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $results = array_merge($results, static::dot($value, $prepend . $key . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }

        return $results;
    }

    /**
     * Convert a flatten "dot" notation array into an expanded array.
     *
     * @param iterable $array
     *
     * @return array
     */
    public static function undot($array) {
        $results = [];

        foreach ($array as $key => $value) {
            static::set($results, $key, $value);
        }

        return $results;
    }

    public static function hash(array $array) {
        array_multisort($array);

        return md5(json_encode($array));
    }

    /**
     * Get a value from the array, and remove it.
     *
     * @param array  $array
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public static function pull(&$array, $key, $default = null) {
        $value = static::get($array, $key, $default);

        static::forget($array, $key);

        return $value;
    }

    /**
     * Get one or a specified number of random values from an array.
     *
     * @param array      $array
     * @param null|int   $number
     * @param bool|false $preserveKeys
     *
     * @throws \InvalidArgumentException
     *
     * @return mixed
     */
    public static function random($array, $number = null, $preserveKeys = false) {
        $requested = is_null($number) ? 1 : $number;

        $count = count($array);

        if ($requested > $count) {
            throw new InvalidArgumentException(
                "You requested {$requested} items, but there are only {$count} items available."
            );
        }

        if (is_null($number)) {
            return $array[array_rand($array)];
        }

        if ((int) $number === 0) {
            return [];
        }

        $keys = array_rand($array, $number);

        $results = [];

        if ($preserveKeys) {
            foreach ((array) $keys as $key) {
                $results[$key] = $array[$key];
            }
        } else {
            foreach ((array) $keys as $key) {
                $results[] = $array[$key];
            }
        }

        return $results;
    }

    /**
     * Get a subset of the items from the given array.
     *
     * @param array        $array
     * @param array|string $keys
     *
     * @return array
     */
    public static function only($array, $keys) {
        return array_intersect_key($array, array_flip((array) $keys));
    }

    /**
     * Alias of array reset.
     *
     * @param array $array
     *
     * @return mixed the value of the first array element, or <b>FALSE</b> if the array is
     */
    public static function head($array) {
        return reset($array);
    }

    public static function implode($glue, $separator, $array) {
        if (!is_array($array)) {
            return $array;
        }
        $string = [];
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $val = carr::implodes(',', $val);
            }
            $string[] = "{$key}{$glue}{$val}";
        }

        return implode($separator, $string);
    }

    public static function implodes($glue, $array) {
        if (!is_array($array)) {
            return $array;
        }
        $ret = '';
        foreach ($array as $item) {
            if (is_array($item)) {
                $ret .= self::implodes($glue, $item) . $glue;
            } else {
                $ret .= $item . $glue;
            }
        }
        //cast wajib: pada larik kosong ini menjadi substr('', 0, -1), yang di
        //PHP 7.4 menjawab false dan baru menjawab '' sejak PHP 8.0
        $ret = (string) substr($ret, 0, 0 - strlen($glue));

        return $ret;
    }

    public static function inArrayWildcard($what, $array) {
        foreach ($array as $pattern) {
            if (cstr::is($pattern, $what)) {
                return true;
            }
        }

        return false;
    }

    public static function isIterable($var) {
        return is_array($var) || $var instanceof \Traversable;
    }

    /**
     * Shuffle the given array and return the result.
     *
     * @param array    $array
     * @param null|int $seed
     *
     * @return array
     */
    public static function shuffle($array, $seed = null) {
        if (is_null($seed)) {
            shuffle($array);
        } else {
            mt_srand($seed);
            shuffle($array);
            mt_srand();
        }

        return $array;
    }

    /**
     * Sort the array using the given callback or "dot" notation.
     *
     * @param array                      $array
     * @param null|callable|array|string $callback
     *
     * @return array
     */
    public static function sort($array, $callback = null) {
        return CCollection::make($array)->sortBy($callback)->all();
    }

    /**
     * Sort the array in descending order using the given callback or "dot" notation.
     *
     * @param array                      $array
     * @param null|callable|array|string $callback
     *
     * @return array
     */
    public static function sortDesc($array, $callback = null) {
        return CCollection::make($array)->sortByDesc($callback)->all();
    }

    /**
     * Recursively sort an array by keys and values.
     *
     * @param array $array
     * @param int   $options
     * @param bool  $descending
     *
     * @return array
     */
    public static function sortRecursive($array, $options = SORT_REGULAR, $descending = false) {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = static::sortRecursive($value, $options, $descending);
            }
        }

        if (static::isAssoc($array)) {
            $descending
                ? krsort($array, $options)
                : ksort($array, $options);
        } else {
            $descending
                ? rsort($array, $options)
                : sort($array, $options);
        }

        return $array;
    }

    /**
     * Convert the array into a query string.
     *
     * @param array $array
     *
     * @return string
     */
    public static function query($array) {
        return http_build_query($array, '', '&', PHP_QUERY_RFC3986);
    }

    public static function reduce($collection, $iteratee, $accumulator = null) {
        if ($collection === null) {
            return null;
        }
        $func = function ($array, $iteratee, $accumulator, $initAccum = null) {
            $array = \is_array($array) ? $array : \iterator_to_array($array);
            $length = \count($array);
            $skipFirstKey = null;
            if ($initAccum && $length) {
                $accumulator = \reset($array);
                $skipFirstKey = \key($array);
            }
            foreach ($array as $key => $value) {
                if ($skipFirstKey !== null && $key === $skipFirstKey) {
                    $skipFirstKey = null;

                    continue;
                }
                $accumulator = $iteratee($accumulator, $value, $key, $array);
            }

            return $accumulator;
        };

        return $func($collection, c::baseIteratee($iteratee), $accumulator, null === $accumulator);
    }

    public static function filter($array, $predicate = null) {
        $iteratee = c::baseIteratee($predicate);
        //$keys = array_keys($array);
        $result = \array_filter(
            \is_array($array) ? $array : \iterator_to_array($array),
            function ($value, $key) use ($array, $iteratee) {
                return $iteratee($value, $key, $array);
            },
            \ARRAY_FILTER_USE_BOTH
        );

        return $result;
    }

    /**
     * Iterates over elements of `collection`, returning the first element
     * `predicate` returns truthy for. The predicate is invoked with three
     * arguments: (value, index|key, collection).
     *
     * @param Traversable $collection the collection to inspect
     * @param callable    $predicate  the function invoked per iteration
     * @param int         $fromIndex  the index to search from
     *
     * @return mixed returns the matched element, else `null`
     *
     * @example
     * <code>
     * $users = [
     *     ['user' => 'barney',  'age' => 36, 'active' => true],
     *     ['user' => 'fred',    'age' => 40, 'active' => false],
     *     ['user' => 'pebbles', 'age' => 1,  'active' => true]
     * ];
     *
     * carr::find($users, function($o) { return $o['age'] < 40; });
     * // => object for 'barney'
     *
     * // The `matches` iteratee shorthand.
     * carr::find($users, ['age' => 1, 'active' => true]);
     * // => object for 'pebbles'
     *
     * // The `matchesProperty` iteratee shorthand.
     * carr::find($users, ['active', false]);
     * // => object for 'fred'
     *
     * // The `property` iteratee shorthand.
     * carr::find($users, 'active');
     * // => object for 'barney'
     * </code>
     */
    public static function find($collection, $predicate = null, $fromIndex = 0) {
        $iteratee = c::baseIteratee($predicate);
        $array = \array_slice(\is_array($collection) ? $collection : \iterator_to_array($collection), $fromIndex);
        foreach ($array as $key => $value) {
            if ($iteratee($value, $key, $collection)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * This method is like `findIndex` except that it iterates over elements
     * of `collection` from right to left.
     *
     * @param array $array     the array to inspect
     * @param mixed $predicate the function invoked per iteration
     * @param int   $fromIndex the index to search from
     *
     * @return int the index of the found element, else `-1`
     *
     * @example
     * <code>
     * $users = [
     *   ['user' => 'barney',  'active' => true ],
     *   ['user' => 'fred',    'active' => false ],
     *   ['user' => 'pebbles', 'active' => false ]
     * ]
     *
     * carr::findLastIndex($users, function($user) { return $user['user'] === 'pebbles'; })
     * // => 2
     * </code>
     */
    public static function findLastIndex(array $array, $predicate, $fromIndex = null) {
        $length = \count($array);
        $index = $fromIndex !== null ? $fromIndex : $length - 1;
        if ($index < 0) {
            $index = \max($length + $index, 0);
        }
        $iteratee = c::baseIteratee($predicate);
        foreach (\array_reverse($array, true) as $key => $value) {
            if ($iteratee($value, $key, $array)) {
                return $index;
            }
            $index--;
        }

        return -1;
    }

    /**
     * Creates an array of values by running each element in `collection` through
     * `iteratee`. The iteratee is invoked with three arguments:
     * (value, index|key, collection).
     *
     * Many lodash-php methods are guarded to work as iteratees for methods like
     * `_::every`, `_::filter`, `_::map`, `_::mapValues`, `_::reject`, and `_::some`.
     *
     * The guarded methods are:
     * `ary`, `chunk`, `curry`, `curryRight`, `drop`, `dropRight`, `every`,
     * `fill`, `invert`, `parseInt`, `random`, `range`, `rangeRight`, `repeat`,
     * `sampleSize`, `slice`, `some`, `sortBy`, `split`, `take`, `takeRight`,
     * `template`, `trim`, `trimEnd`, `trimStart`, and `words`
     *
     * @param array|object          $collection the collection to iterate over
     * @param callable|string|array $iteratee   the function invoked per iteration
     *
     * @return array returns the new mapped array
     *
     * @example
     * <code>
     * function square(int $n) {
     *   return $n * $n;
     * }
     *
     * carr::map([4, 8], $square);
     * // => [16, 64]
     *
     * carr::map((object) ['a' => 4, 'b' => 8], $square);
     * // => [16, 64] (iteration order is not guaranteed)
     *
     * $users = [
     *   [ 'user' => 'barney' ],
     *   [ 'user' => 'fred' ]
     * ];
     *
     * // The `property` iteratee shorthand.
     * carr::map($users, 'user');
     * // => ['barney', 'fred']
     * </code>
     */
    public static function map($collection, $iteratee) {
        $values = [];
        if (\is_array($collection)) {
            $values = $collection;
        } elseif ($collection instanceof \Traversable) {
            $values = \iterator_to_array($collection);
        } elseif (\is_object($collection)) {
            $values = \get_object_vars($collection);
        }

        $callable = c::baseIteratee($iteratee);
        $keys = \array_keys($values);
        $items = \array_map(function ($value, $index) use ($callable, $collection) {
            return $callable($value, $index, $collection);
        }, $values, $keys);

        return array_combine($keys, $items);
    }

    /**
     * Run a map transforms over each of the items.
     *
     * @param array        $collection
     * @param string|array $transforms
     *
     * @return array
     */
    public static function mapTransform($collection, $transforms) {
        return static::map($collection, function ($item) use ($transforms) {
            return c::manager()->transform()->call($transforms, $item);
        });
    }

    /**
     * Creates a new array concatenating `array` with any additional arrays
     * and/or values.
     *
     * @param array             $array  the array to concatenate
     * @param array<int, mixed> $values the values to concatenate
     *
     * @return array returns the new concatenated array
     *
     * @example
     * <code>
     * $array = [1];
     * $other = carr::concat($array, 2, [3], [[4]]);
     *
     * var_dump($other)
     * // => [1, 2, 3, [4]]
     *
     * var_dump($array)
     * // => [1]
     * </code>
     */
    public static function concat($array, ...$values) {
        $check = function ($value) {
            return \is_array($value) ? $value : [$value];
        };

        return \array_merge($check($array), ...\array_map($check, $values));
    }

    /**
     * Checks if `predicate` returns truthy for **any** element of `collection`.
     * Iteration is stopped once `predicate` returns truthy. The predicate is
     * invoked with three arguments: (value, index|key, collection).
     *
     * @param iterable              $collection the collection to iterate over
     * @param callable|string|array $predicate  the function invoked per iteration
     *
     * @return bool returns `true` if any element passes the predicate check, else `false`
     *
     * @example
     * <code>
     * some([null, 0, 'yes', false], , function ($value) { return \is_bool($value); }));
     * // => true
     *
     * $users = [
     *   ['user' => 'barney', 'active' => true],
     *   ['user' => 'fred',   'active' => false]
     * ];
     *
     * // The `matches` iteratee shorthand.
     * some($users, ['user' => 'barney', 'active' => false ]);
     * // => false
     *
     * // The `matchesProperty` iteratee shorthand.
     * some($users, ['active', false]);
     * // => true
     *
     * // The `property` iteratee shorthand.
     * some($users, 'active');
     * // => true
     * </code>
     */
    public static function some($collection, $predicate = null) {
        $iteratee = c::baseIteratee($predicate);
        foreach ($collection as $key => $value) {
            if ($iteratee($value, $key, $collection)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Iterates over elements of `collection` and invokes `iteratee` for each element.
     * The iteratee is invoked with three arguments: (value, index|key, collection).
     * Iteratee functions may exit iteration early by explicitly returning `false`.
     *
     * **Note:** As with other "Collections" methods, objects with a "length"
     * property are iterated like arrays. To avoid this behavior use `forIn`
     * or `forOwn` for object iteration.
     *
     * @param array|iterable|object $collection the collection to iterate over
     * @param callable              $iteratee   the function invoked per iteration
     *
     * @return array|object returns `collection`
     *
     * @example
     * <code>
     * carr::each([1, 2], function ($value) { echo $value; })
     * // => Echoes `1` then `2`.
     *
     * carr::each((object) ['a' => 1, 'b' => 2], function ($value, $key) { echo $key; });
     * // => Echoes 'a' then 'b' (iteration order is not guaranteed).
     * </code>
     */
    public static function each($collection, callable $iteratee) {
        $values = \is_object($collection) ? \get_object_vars($collection) : $collection;
        /* @var array $values */
        foreach ($values as $index => $value) {
            if (false === $iteratee($value, $index, $collection)) {
                break;
            }
        }

        return $collection;
    }

    /**
     * Push an item onto the beginning of an array.
     *
     * @param array $array
     * @param mixed $value
     * @param mixed $key
     *
     * @return array
     */
    public static function prepend($array, $value, $key = null) {
        if (func_num_args() == 2) {
            array_unshift($array, $value);
        } else {
            $array = [$key => $value] + $array;
        }

        return $array;
    }

    /**
     * @param array $array
     *
     * @return int
     */
    public static function count($array) {
        return count($array);
    }

    public static function arrayMergeRecursiveDistinct(array &$array1, array &$array2) {
        $merged = $array1;
        foreach ($array2 as $key => &$value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = static::arrayMergeRecursiveDistinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * @param array $array
     *
     * @return array
     */
    public static function mirror(array $array) {
        return array_combine($array, $array);
    }

    /**
     * @param array $array
     *
     * @return array
     */
    public static function transpose(array $array): array {
        if (count($array) === 1) {
            $array = static::first($array);

            return array_map(function ($element) {
                return [$element];
            }, $array);
        }

        $numHits = count($array[0]);
        $groups = array_keys($array);
        $result = [];
        for ($hit = 0; $hit < $numHits; $hit++) {
            $group = [];
            foreach ($groups as $groupName) {
                $group[$groupName] = $array[$groupName][$hit];
            }
            $result[] = $group;
        }

        return $result;
    }
}

// End carr
