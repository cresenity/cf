<?php

/**
 * Description of Finder.
 */
class CApp_React_Finder {
    /**
     * Hint path delimiter value.
     *
     * @var string
     */
    const HINT_PATH_DELIMITER = '::';

    /**
     * The array of active react paths.
     *
     * @var array
     */
    protected $paths;

    /**
     * The array of react that have been located.
     *
     * @var array
     */
    protected $reacts = [];

    /**
     * The namespace to file path hints.
     *
     * @var array
     */
    protected $hints = [];

    /**
     * Register a react extension with the finder.
     *
     * @var string[]
     */
    protected $extensions = ['js', 'jsx'];

    /**
     * @var CApp_React_Finder
     */
    private static $instance;

    /**
     * @return CApp_React_Finder
     */
    public static function instance() {
        if (static::$instance == null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Create a new file react loader instance.
     *
     * @return void
     */
    public function __construct() {
        $this->paths = [];
    }

    /**
     * Get the fully qualified location of the react.
     *
     * @param string $name
     *
     * @return string
     */
    public function find($name) {
        if (isset($this->reacts[$name])) {
            return $this->reacts[$name];
        }

        if ($this->hasHintInformation($name = trim($name))) {
            return $this->reacts[$name] = $this->findNamespacedReact($name);
        }

        return $this->reacts[$name] = $this->findInPaths($name, $this->paths);
    }

    /**
     * Get the path to a template with a named path.
     *
     * @param string $name
     *
     * @return string
     */
    protected function findNamespacedReact($name) {
        list($namespace, $react) = $this->parseNamespaceSegments($name);

        return $this->findInPaths($react, $this->hints[$namespace]);
    }

    /**
     * Get the segments of a template with a named path.
     *
     * @param string $name
     *
     * @throws \InvalidArgumentException
     *
     * @return array
     */
    protected function parseNamespaceSegments($name) {
        $segments = explode(static::HINT_PATH_DELIMITER, $name);

        if (count($segments) !== 2) {
            throw new InvalidArgumentException("View [{$name}] has an invalid name.");
        }

        if (!isset($this->hints[$segments[0]])) {
            throw new InvalidArgumentException("No hint path defined for [{$segments[0]}].");
        }

        return $segments;
    }

    /**
     * Find the given react in the list of paths.
     *
     * @param string $name
     * @param array  $paths
     *
     * @throws \InvalidArgumentException
     *
     * @return string
     */
    protected function findInPaths($name, $paths) {
        $cfPath = CF::getDirs('media', null, false);

        $paths = array_merge($cfPath, $paths);

        foreach ((array) $paths as $path) {
            foreach ($this->getPossibleReactFiles($name) as $file) {
                $reactPath = rtrim($path, '/');
                $intermediatePath = CF::config('cresjs.react.path');
                if ($intermediatePath) {
                    $reactPath .= '/' . trim($intermediatePath, '/');
                }

                $reactPath .= '/' . $file;
                if (file_exists($reactPath)) {
                    return $reactPath;
                }
            }
        }

        throw new InvalidArgumentException("Component [{$name}] not found.");
    }

    /**
     * Get an array of possible react files.
     *
     * @param string $name
     *
     * @return array
     */
    protected function getPossibleReactFiles($name) {
        return array_map(function ($extension) use ($name) {
            return str_replace('.', '/', $name) . '.' . $extension;
        }, $this->extensions);
    }

    /**
     * Add a location to the finder.
     *
     * @param string $location
     *
     * @return void
     */
    public function addLocation($location) {
        $this->paths[] = $this->resolvePath($location);
    }

    /**
     * Prepend a location to the finder.
     *
     * @param string $location
     *
     * @return void
     */
    public function prependLocation($location) {
        array_unshift($this->paths, $this->resolvePath($location));
    }

    /**
     * Resolve the path.
     *
     * @param string $path
     *
     * @return string
     */
    protected function resolvePath($path) {
        return realpath($path) ?: $path;
    }

    /**
     * Add a namespace hint to the finder.
     *
     * @param string       $namespace
     * @param string|array $hints
     *
     * @return void
     */
    public function addNamespace($namespace, $hints) {
        $hints = (array) $hints;

        if (isset($this->hints[$namespace])) {
            $hints = array_merge($this->hints[$namespace], $hints);
        }

        $this->hints[$namespace] = $hints;
    }

    /**
     * Prepend a namespace hint to the finder.
     *
     * @param string       $namespace
     * @param string|array $hints
     *
     * @return void
     */
    public function prependNamespace($namespace, $hints) {
        $hints = (array) $hints;

        if (isset($this->hints[$namespace])) {
            $hints = array_merge($hints, $this->hints[$namespace]);
        }

        $this->hints[$namespace] = $hints;
    }

    /**
     * Replace the namespace hints for the given namespace.
     *
     * @param string       $namespace
     * @param string|array $hints
     *
     * @return void
     */
    public function replaceNamespace($namespace, $hints) {
        $this->hints[$namespace] = (array) $hints;
    }

    /**
     * Register an extension with the react finder.
     *
     * @param string $extension
     *
     * @return void
     */
    public function addExtension($extension) {
        if (($index = array_search($extension, $this->extensions)) !== false) {
            unset($this->extensions[$index]);
        }

        array_unshift($this->extensions, $extension);
    }

    /**
     * Returns whether or not the react name has any hint information.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasHintInformation($name) {
        return strpos($name, static::HINT_PATH_DELIMITER) > 0;
    }

    /**
     * Flush the cache of located reacts.
     *
     * @return void
     */
    public function flush() {
        $this->reacts = [];
    }

    /**
     * Set the active react paths.
     *
     * @param array $paths
     *
     * @return $this
     */
    public function setPaths($paths) {
        $this->paths = $paths;

        return $this;
    }

    /**
     * Get the active react paths.
     *
     * @return array
     */
    public function getPaths() {
        return $this->paths;
    }

    /**
     * Get the reacts that have been located.
     *
     * @return array
     */
    public function getReacts() {
        return $this->reacts;
    }

    /**
     * Get the namespace to file path hints.
     *
     * @return array
     */
    public function getHints() {
        return $this->hints;
    }

    /**
     * Get registered extensions.
     *
     * @return array
     */
    public function getExtensions() {
        return $this->extensions;
    }
}
