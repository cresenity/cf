<?php

/**
 * Description of DynamicComponent.
 */
class CView_Component_DynamicComponent extends CView_ComponentAbstract {
    /**
     * The name of the component.
     *
     * @var string
     */
    public $component;

    /**
     * The component tag compiler instance.
     *
     * @var CView_Compiler_BladeCompiler
     */
    protected static $compiler;

    /**
     * The cached component classes.
     *
     * @var array
     */
    protected static $componentClasses = [];

    /**
     * Create a new component instance.
     *
     * @param mixed $component
     *
     * @return void
     */
    public function __construct($component) {
        $this->component = $component;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \CView_View|string
     */
    public function render() {
        $template = <<<'EOF'
<?php extract(collect($attributes->getAttributes())->mapWithKeys(function ($value, $key) { return [cstr::camel(str_replace([':', '.'], ' ', $key)) => $value]; })->all(), EXTR_SKIP); ?>
{{ props }}
<x-{{ component }} {{ bindings }} {{ attributes }}>
{{ slots }}
{{ defaultSlot }}
</x-{{ component }}>
EOF;

        return function ($data) use ($template) {
            $bindings = $this->bindings($class = $this->classForComponent());

            return str_replace(
                [
                    '{{ component }}',
                    '{{ props }}',
                    '{{ bindings }}',
                    '{{ attributes }}',
                    '{{ slots }}',
                    '{{ defaultSlot }}',
                ],
                [
                    $this->component,
                    $this->compileProps($bindings),
                    $this->compileBindings($bindings),
                    class_exists($class) ? '{{ $attributes }}' : '',
                    $this->compileSlots($data['__cview_slots']),
                    '{{ isset($slot) ? $slot : "" }}',
                ],
                $template
            );
        };
    }

    /**
     * Compile the @props directive for the component.
     *
     * @param array $bindings
     *
     * @return string
     */
    protected function compileProps(array $bindings) {
        if (empty($bindings)) {
            return '';
        }

        return '@props(' . '[\'' . implode('\',\'', c::collect($bindings)->map(function ($dataKey) {
            return cstr::camel($dataKey);
        })->all()) . '\']' . ')';
    }

    /**
     * Compile the bindings for the component.
     *
     * @param array $bindings
     *
     * @return string
     */
    protected function compileBindings(array $bindings) {
        return c::collect($bindings)->map(function ($key) {
            return ':' . $key . '="$' . cstr::camel(str_replace([':', '.'], ' ', $key)) . '"';
        })->implode(' ');
    }

    /**
     * Compile the slots for the component.
     *
     * @param array $slots
     *
     * @return string
     */
    protected function compileSlots(array $slots) {
        return c::collect($slots)->map(function ($slot, $name) {
            return $name === '__default' ? null : '<x-slot name="' . $name . '" ' . ((string) $slot->attributes) . '>{{ $' . $name . ' }}</x-slot>';
        })->filter()->implode(PHP_EOL);
    }

    /**
     * Get the class for the current component.
     *
     * @return string
     */
    protected function classForComponent() {
        if (isset(static::$componentClasses[$this->component])) {
            return static::$componentClasses[$this->component];
        }

        return static::$componentClasses[$this->component] = $this->compiler()->componentClass($this->component);
    }

    /**
     * Get the names of the variables that should be bound to the component.
     *
     * @param string $class
     *
     * @return array
     */
    protected function bindings($class) {
        list($data, $attributes) = $this->compiler()->partitionDataAndAttributes($class, $this->attributes->getAttributes());

        return array_keys($data->all());
    }

    /**
     * Get an instance of the Blade tag compiler.
     *
     * @return \CView_Compiler_ComponentTagCompiler
     */
    protected function compiler() {
        if (!static::$compiler) {
            static::$compiler = new CView_Compiler_ComponentTagCompiler(
                CView::blade()->getClassComponentAliases(),
                CView::blade()->getClassComponentNamespaces(),
            );
        }

        return static::$compiler;
    }
}
