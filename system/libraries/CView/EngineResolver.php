<?php

/**
 * Description of EngineResolver.
 */
class CView_EngineResolver {
    /**
     * The array of engine resolvers.
     *
     * @var array
     */
    protected $resolvers = [];

    /**
     * The resolved engine instances.
     *
     * @var array
     */
    protected $resolved = [];

    /**
     * @var CView_EngineResolver
     */
    private static $instance;

    /**
     * @return CView_EngineResolver
     */
    public static function instance() {
        if (static::$instance == null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function __construct() {
        $this->registerFileEngine();
        $this->registerPhpEngine();
        $this->registerBladeEngine();
    }

    /**
     * Register a new engine resolver.
     *
     * The engine string typically corresponds to a file extension.
     *
     * @param string   $engine
     * @param \Closure $resolver
     *
     * @return void
     */
    public function register($engine, Closure $resolver) {
        unset($this->resolved[$engine]);

        $this->resolvers[$engine] = $resolver;
    }

    /**
     * Resolve an engine instance by name.
     *
     * @param string $engine
     *
     * @throws \InvalidArgumentException
     *
     * @return CView_EngineAbstract
     */
    public function resolve($engine) {
        if (isset($this->resolved[$engine])) {
            return $this->resolved[$engine];
        }

        if (isset($this->resolvers[$engine])) {
            return $this->resolved[$engine] = call_user_func($this->resolvers[$engine]);
        }

        throw new InvalidArgumentException("Engine [{$engine}] not found.");
    }

    /**
     * Register the file engine implementation.
     *
     * @return void
     */
    public function registerFileEngine() {
        $this->register('file', function () {
            return new CView_Engine_FileEngine();
        });
    }

    /**
     * Register the PHP engine implementation.
     *
     * @return void
     */
    public function registerPhpEngine() {
        $this->register('php', function () {
            return new CView_Engine_PhpEngine();
        });
    }

    /**
     * Register the Blade engine implementation.
     *
     * @return void
     */
    protected function registerBladeEngine() {
        $this->register('blade', function () {
            return new CView_Engine_CompilerEngine();
        });
    }
}
