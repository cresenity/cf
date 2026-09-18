<?php

defined('SYSPATH') or die('No direct access allowed.');
use Symfony\Component\VarDumper\VarDumper;

trait CHTTP_Trait_InteractsWithInput {
    /**
     * Retrieve a server variable from the request.
     *
     * @param string            $key
     * @param null|string|array $default
     *
     * @return null|string|array
     */
    public function server($key = null, $default = null) {
        return $this->retrieveItem('server', $key, $default);
    }

    /**
     * Determine if a header is set on the request.
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasHeader($key) {
        return !is_null($this->header($key));
    }

    /**
     * Retrieve a header from the request.
     *
     * @param string            $key
     * @param null|string|array $default
     *
     * @return null|string|array
     */
    public function header($key = null, $default = null) {
        return $this->retrieveItem('headers', $key, $default);
    }

    /**
     * Get the bearer token from the request headers.
     *
     * @return null|string
     */
    public function bearerToken() {
        $header = $this->header('Authorization', '');

        $position = strrpos($header, 'Bearer ');
        if ($position !== false) {
            $header = substr($header, $position + 7);

            return cstr::contains($header, ',') ? strstr($header, ',', true) : $header;
        }

        return null;
    }

    /**
     * Determine if the request contains a given input item key.
     *
     * @param string|array $key
     *
     * @return bool
     */
    public function exists($key) {
        return $this->has($key);
    }

    /**
     * Determine if the request contains a given input item key.
     *
     * @param string|array $key
     *
     * @return bool
     */
    public function has($key) {
        $keys = is_array($key) ? $key : func_get_args();

        $input = $this->all();

        foreach ($keys as $value) {
            if (!carr::has($input, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the request contains any of the given inputs.
     *
     * @param string|array $keys
     *
     * @return bool
     */
    public function hasAny($keys) {
        $keys = is_array($keys) ? $keys : func_get_args();
        $input = $this->all();

        return carr::hasAny($input, $keys);
    }

    /**
     * Apply the callback if the request contains the given input item key.
     *
     * @param string        $key
     * @param callable      $callback
     * @param null|callable $default
     *
     * @return $this|mixed
     */
    public function whenHas($key, $callback, $default = null) {
        if ($this->has($key)) {
            return $callback(c::get($this->all(), $key)) ?: $this;
        }

        if ($default) {
            return $default();
        }

        return $this;
    }

    /**
     * Determine if the request contains a non-empty value for an input item.
     *
     * @param string|array $key
     *
     * @return bool
     */
    public function filled($key) {
        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $value) {
            if ($this->isEmptyString($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the request contains an empty value for an input item.
     *
     * @param string|array $key
     *
     * @return bool
     */
    public function isNotFilled($key) {
        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $value) {
            if (!$this->isEmptyString($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the request contains a non-empty value for any of the given inputs.
     *
     * @param string|array $keys
     *
     * @return bool
     */
    public function anyFilled($keys) {
        $keys = is_array($keys) ? $keys : func_get_args();

        foreach ($keys as $key) {
            if ($this->filled($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply the callback if the request contains a non-empty value for the given input item key.
     *
     * @param string        $key
     * @param callable      $callback
     * @param null|callable $default
     *
     * @return $this|mixed
     */
    public function whenFilled($key, $callback, $default = null) {
        if ($this->filled($key)) {
            return $callback(c::get($this->all(), $key)) ?: $this;
        }

        if ($default) {
            return $default();
        }

        return $this;
    }

    /**
     * Determine if the request is missing a given input item key.
     *
     * @param string|array $key
     *
     * @return bool
     */
    public function missing($key) {
        $keys = is_array($key) ? $key : func_get_args();

        return !$this->has($keys);
    }

    /**
     * Determine if the given input key is an empty string for "has".
     *
     * @param string $key
     *
     * @return bool
     */
    protected function isEmptyString($key) {
        $value = $this->input($key);

        return !is_bool($value) && !is_array($value) && trim((string) $value) === '';
    }

    /**
     * Get the keys for all of the input and files.
     *
     * @return array
     */
    public function keys() {
        return array_merge(array_keys($this->input()), $this->files->keys());
    }

    /**
     * Get all of the input and files for the request.
     *
     * @param array|mixed $keys
     *
     * @return array
     */
    public function all($keys = null) {
        $input = array_replace_recursive($this->input(), $this->allFiles());

        if (!$keys) {
            return $input;
        }

        $results = [];

        foreach (is_array($keys) ? $keys : func_get_args() as $key) {
            carr::set($results, $key, carr::get($input, $key));
        }

        return $results;
    }

    /**
     * Retrieve an input item from the request.
     *
     * @param null|string $key
     * @param mixed       $default
     *
     * @return mixed
     */
    public function input($key = null, $default = null) {
        return c::get(
            $this->getInputSource()->all() + $this->query->all(),
            $key,
            $default
        );
    }

    /**
     * Retrieve input from the request as a Stringable instance.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return \CBase_String
     */
    public function str($key, $default = null) {
        return $this->string($key, $default);
    }

    /**
     * Retrieve input from the request as a Stringable instance.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return \CBase_String
     */
    public function string($key, $default = null) {
        return cstr::of($this->input($key, $default));
    }

    /**
     * Retrieve input as a boolean value.
     *
     * Returns true when value is "1", "true", "on", and "yes". Otherwise, returns false.
     *
     * @param null|string $key
     * @param bool        $default
     *
     * @return bool
     */
    public function boolean($key = null, $default = false) {
        return filter_var($this->input($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Retrieve input as an integer value.
     *
     * @param string $key
     * @param int    $default
     *
     * @return int
     */
    public function integer($key, $default = 0) {
        return intval($this->input($key, $default));
    }

    /**
     * Retrieve input as a float value.
     *
     * @param string $key
     * @param float  $default
     *
     * @return float
     */
    public function float($key, $default = 0.0) {
        return floatval($this->input($key, $default));
    }

    /**
     * Retrieve input from the request as a Carbon instance.
     *
     * @param string      $key
     * @param null|string $format
     * @param null|string $tz
     *
     * @throws \Carbon\Exceptions\InvalidFormatException
     *
     * @return null|\CCarbon
     */
    public function date($key, $format = null, $tz = null) {
        if ($this->isNotFilled($key)) {
            return null;
        }

        if (is_null($format)) {
            return CCarbon::parse($this->input($key), $tz);
        }

        return CCarbon::createFromFormat($format, $this->input($key), $tz);
    }

    /**
     * Retrieve input from the request as an enum.
     *
     * @template TEnum
     *
     * @param string              $key
     * @param class-string<TEnum> $enumClass
     *
     * @return null|TEnum
     */
    public function enum($key, $enumClass) {
        if ($this->isNotFilled($key)
            || !function_exists('enum_exists')
            || !enum_exists($enumClass)
            || !method_exists($enumClass, 'tryFrom')
        ) {
            return null;
        }

        return $enumClass::tryFrom($this->input($key));
    }

    /**
     * Retrieve input from the request as a collection.
     *
     * @param null|array|string $key
     *
     * @return \CCollection
     */
    public function collect($key = null) {
        return c::collect(is_array($key) ? $this->only($key) : $this->input($key));
    }

    /**
     * Get a subset containing the provided keys with values from the input data.
     *
     * @param array|mixed $keys
     *
     * @return array
     */
    public function only($keys) {
        $results = [];

        $input = $this->all();

        $placeholder = new stdClass();

        foreach (is_array($keys) ? $keys : func_get_args() as $key) {
            $value = c::get($input, $key, $placeholder);

            if ($value !== $placeholder) {
                carr::set($results, $key, $value);
            }
        }

        return $results;
    }

    /**
     * Get all of the input except for a specified array of items.
     *
     * @param array|mixed $keys
     *
     * @return array
     */
    public function except($keys) {
        $keys = is_array($keys) ? $keys : func_get_args();

        $results = $this->all();

        carr::forget($results, $keys);

        return $results;
    }

    /**
     * Retrieve a query string item from the request.
     *
     * @param string            $key
     * @param null|string|array $default
     *
     * @return null|string|array
     */
    public function query($key = null, $default = null) {
        return $this->retrieveItem('query', $key, $default);
    }

    /**
     * Retrieve a request payload item from the request.
     *
     * @param string            $key
     * @param null|string|array $default
     *
     * @return null|string|array
     */
    public function post($key = null, $default = null) {
        return $this->retrieveItem('request', $key, $default);
    }

    /**
     * Determine if a cookie is set on the request.
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasCookie($key) {
        return !is_null($this->cookie($key));
    }

    /**
     * Retrieve a cookie from the request.
     *
     * @param null|string       $key
     * @param null|string|array $default
     *
     * @return null|string|array
     */
    public function cookie($key = null, $default = null) {
        return $this->retrieveItem('cookies', $key, $default);
    }

    /**
     * Get an array of all of the files on the request.
     *
     * @return array
     */
    public function allFiles() {
        $files = $this->files->all();

        return $this->convertedFiles ? $this->convertedFiles : $this->convertedFiles = $this->convertUploadedFiles($files);
    }

    /**
     * Convert the given array of Symfony UploadedFiles to custom UploadedFiles.
     *
     * @param array $files
     *
     * @return array
     */
    protected function convertUploadedFiles(array $files) {
        return array_map(function ($file) {
            if (is_null($file) || (is_array($file) && empty(array_filter($file)))) {
                return $file;
            }

            return is_array($file) ? $this->convertUploadedFiles($file) : CHTTP_UploadedFile::createFromBase($file);
        }, $files);
    }

    /**
     * Determine if the uploaded data contains a file.
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasFile($key) {
        if (!is_array($files = $this->file($key))) {
            $files = [$files];
        }

        foreach ($files as $file) {
            if ($this->isValidFile($file)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check that the given file is a valid file instance.
     *
     * @param mixed $file
     *
     * @return bool
     */
    protected function isValidFile($file) {
        return $file instanceof SplFileInfo && $file->getPath() !== '';
    }

    /**
     * Retrieve a file from the request.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return null|CHTTP_UploadedFile|array
     */
    public function file($key = null, $default = null) {
        return c::get($this->allFiles(), $key, $default);
    }

    /**
     * Retrieve a parameter item from a given source.
     *
     * @param string            $source
     * @param null|string       $key
     * @param null|string|array $default
     *
     * @return null|string|array
     */
    protected function retrieveItem($source, $key, $default) {
        if (is_null($key)) {
            return $this->$source->all();
        }

        return $this->$source->get($key, $default);
    }

    /**
     * Dump the request items and end the script.
     *
     * @param mixed $keys
     *
     * @return never
     */
    public function dd(...$keys) {
        $this->dump(...$keys);

        exit(1);
    }

    /**
     * Dump the items.
     *
     * @param mixed $keys
     *
     * @return $this
     */
    public function dump($keys = []) {
        $keys = is_array($keys) ? $keys : func_get_args();

        VarDumper::dump(count($keys) > 0 ? $this->only($keys) : $this->all());

        return $this;
    }
}
