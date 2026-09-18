<?php

defined('SYSPATH') or die('No direct access allowed.');

abstract class CView_ComponentAbstract {
    /**
     * The component alias name.
     *
     * @var string
     */
    public $componentName;

    /**
     * The component attributes.
     *
     * @var \CView_ComponentAttributeBag
     */
    public $attributes;

    /**
     * The properties / methods that should not be exposed to the component.
     *
     * @var array
     */
    protected $except = [];

    /**
     * The component resolver callback.
     *
     * @var (\Closure(string, array): CView_ComponentAbstract)|null
     */
    protected static $componentsResolver;

    /**
     * The cache of blade view names, keyed by contents.
     *
     * @var array<string, string>
     */
    protected static $bladeViewCache = [];

    /**
     * The cache of public property names, keyed by class.
     *
     * @var array
     */
    protected static $propertyCache = [];

    /**
     * The cache of public method names, keyed by class.
     *
     * @var array
     */
    protected static $methodCache = [];

    /**
     * The cache of constructor parameters, keyed by class.
     *
     * @var array<class-string, array<int, string>>
     */
    protected static $constructorParametersCache = [];

    /**
     * Get the view / view contents that represent the component.
     *
     * @return \CView_ViewInterface|\CInterface_Htmlable|\Closure|string
     */
    abstract public function render();

    /**
     * Resolve the component instance with the given data.
     *
     * @param array $data
     *
     * @return static
     */
    public static function resolve($data) {
        if (static::$componentsResolver) {
            return call_user_func(static::$componentsResolver, static::class, $data);
        }

        $parameters = static::extractConstructorParameters();

        $dataKeys = array_keys($data);

        if (empty(array_diff($parameters, $dataKeys))) {
            $params = array_values(array_intersect_key($data, array_flip($parameters)));

            return new static(...$params);
        }

        return CContainer::getInstance()->make(static::class, $data);
    }

    /**
     * Extract the constructor parameters for the component.
     *
     * @return array
     */
    protected static function extractConstructorParameters() {
        if (!isset(static::$constructorParametersCache[static::class])) {
            $class = new ReflectionClass(static::class);

            $constructor = $class->getConstructor();

            static::$constructorParametersCache[static::class] = $constructor
                ? c::collect($constructor->getParameters())->map->getName()->all()
                : [];
        }

        return static::$constructorParametersCache[static::class];
    }

    /**
     * Resolve the Blade view or view file that should be used when rendering the component.
     *
     * @return CView_View|\CInterface_Htmlable|\Closure|string
     */
    public function resolveView() {
        $view = $this->render();

        if ($view instanceof CView_View) {
            return $view;
        }

        if ($view instanceof CInterface_Htmlable) {
            return $view;
        }

        $resolver = function ($view) {
            return $this->extractBladeViewFromString($view);
        };

        return $view instanceof Closure ? function (array $data = []) use ($view, $resolver) {
            return $resolver($view($data));
        }
        : $resolver($view);
    }

    /**
     * Create a Blade view with the raw component string content.
     *
     * @param string $contents
     *
     * @return string
     */
    protected function extractBladeViewFromString($contents) {
        $key = sprintf('%s::%s', static::class, $contents);

        if (isset(static::$bladeViewCache[$key])) {
            return static::$bladeViewCache[$key];
        }

        if (strlen($contents) <= PHP_MAXPATHLEN && CView::factory()->exists($contents)) {
            return static::$bladeViewCache[$key] = $contents;
        }

        return static::$bladeViewCache[$key] = $this->createBladeViewFromString(CView::factory(), $contents);
    }

    /**
     * Create a Blade view with the raw component string content.
     *
     * @param CView_Factory $factory
     * @param string        $contents
     *
     * @return string
     */
    protected function createBladeViewFromString($factory, $contents) {
        $directory = $factory->compiledPath();
        $factory->addNamespace(
            '__components',
            $directory
        );

        if (!is_file($viewFile = $directory . '/' . sha1($contents) . '.blade.php')) {
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($viewFile, $contents);
        }

        return '__components::' . basename($viewFile, '.blade.php');
    }

    /**
     * Get the data that should be supplied to the view.
     *
     *
     * @return array
     */
    public function data() {
        $this->attributes = $this->attributes ?: new CView_ComponentAttributeBag();

        return array_merge($this->extractPublicProperties(), $this->extractPublicMethods());
    }

    /**
     * Extract the public properties for the component.
     *
     * @return array
     */
    protected function extractPublicProperties() {
        $class = get_class($this);

        if (!isset(static::$propertyCache[$class])) {
            $reflection = new ReflectionClass($this);

            static::$propertyCache[$class] = c::collect($reflection->getProperties(ReflectionProperty::IS_PUBLIC))
                ->reject(function (ReflectionProperty $property) {
                    return $property->isStatic();
                })
                ->reject(function (ReflectionProperty $property) {
                    return $this->shouldIgnore($property->getName());
                })
                ->map(function (ReflectionProperty $property) {
                    return $property->getName();
                })->all();
        }

        $values = [];

        foreach (static::$propertyCache[$class] as $property) {
            $values[$property] = $this->{$property};
        }

        return $values;
    }

    /**
     * Extract the public methods for the component.
     *
     * @return array
     */
    protected function extractPublicMethods() {
        $class = get_class($this);

        if (!isset(static::$methodCache[$class])) {
            $reflection = new ReflectionClass($this);

            static::$methodCache[$class] = c::collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
                ->reject(function (ReflectionMethod $method) {
                    return $this->shouldIgnore($method->getName());
                })
                ->map(function (ReflectionMethod $method) {
                    return $method->getName();
                });
        }

        $values = [];

        foreach (static::$methodCache[$class] as $method) {
            $values[$method] = $this->createVariableFromMethod(new ReflectionMethod($this, $method));
        }

        return $values;
    }

    /**
     * Create a callable variable from the given method.
     *
     * @param \ReflectionMethod $method
     *
     * @return mixed
     */
    protected function createVariableFromMethod(ReflectionMethod $method) {
        return $method->getNumberOfParameters() === 0
                        ? $this->createInvokableVariable($method->getName())
                        : Closure::fromCallable([$this, $method->getName()]);
    }

    /**
     * Create an invokable, toStringable variable for the given component method.
     *
     * @param string $method
     *
     * @return CView_InvokableComponentVariable
     */
    protected function createInvokableVariable($method) {
        return new CView_InvokableComponentVariable(function () use ($method) {
            return $this->{$method}();
        });
    }

    /**
     * Determine if the given property / method should be ignored.
     *
     * @param string $name
     *
     * @return bool
     */
    protected function shouldIgnore($name) {
        return cstr::startsWith($name, '__')
               || in_array($name, $this->ignoredMethods());
    }

    /**
     * Get the methods that should be ignored.
     *
     * @return array
     */
    protected function ignoredMethods() {
        return array_merge([
            'data',
            'render',
            'resolveView',
            'shouldRender',
            'view',
            'withName',
            'withAttributes',
            'flushCache',
            'forgetFactory',
            'forgetComponentsResolver',
            'resolveComponentsUsing',
        ], $this->except);
    }

    /**
     * Set the component alias name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function withName($name) {
        $this->componentName = $name;

        return $this;
    }

    /**
     * Set the extra attributes that the component should make available.
     *
     * @param array $attributes
     *
     * @return $this
     */
    public function withAttributes(array $attributes) {
        $this->attributes = $this->attributes ?: new CView_ComponentAttributeBag();

        $this->attributes->setAttributes($attributes);

        return $this;
    }

    /**
     * Get a new attribute bag instance.
     *
     * @param array $attributes
     *
     * @return \CView_ComponentAttributeBag
     */
    protected function newAttributeBag(array $attributes = []) {
        return new CView_ComponentAttributeBag($attributes);
    }

    /**
     * Determine if the component should be rendered.
     *
     * @return bool
     */
    public function shouldRender() {
        return true;
    }

    /**
     * Get the evaluated view contents for the given view.
     *
     * @param null|string                                   $view
     * @param \Illuminate\Contracts\Support\Arrayable|array $data
     * @param array                                         $mergeData
     *
     * @return \CView_ViewInterface
     */
    public function view($view, $data = [], $mergeData = []) {
        return CView::factory()->make($view, $data, $mergeData);
    }

    /**
     * Flush the component's cached state.
     *
     * @return void
     */
    public static function flushCache() {
        static::$bladeViewCache = [];
        static::$constructorParametersCache = [];
        static::$methodCache = [];
        static::$propertyCache = [];
    }

    /**
     * Forget the component's resolver callback.
     *
     * @return void
     *
     * @internal
     */
    public static function forgetComponentsResolver() {
        static::$componentsResolver = null;
    }

    /**
     * Set the callback that should be used to resolve components within views.
     *
     * @param \Closure $resolver
     *
     * @return void
     *
     * @internal
     */
    public static function resolveComponentsUsing($resolver) {
        static::$componentsResolver = $resolver;
    }
}
