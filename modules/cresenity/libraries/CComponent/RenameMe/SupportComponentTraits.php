<?php

class CComponent_RenameMe_SupportComponentTraits {
    public static function init() {
        return new static;
    }

    protected $componentIdMethodMap = [];

    public function __construct() {
        CComponent_Manager::instance()->listen('component.hydrate', function ($component) {
            $component->initializeTraits();

            foreach (c::classUsesRecursive($component) as $trait) {
                $hooks = [
                    'hydrate',
                    'mount',
                    'updating',
                    'updated',
                    'rendering',
                    'rendered',
                    'dehydrate',
                ];

                foreach ($hooks as $hook) {
                    $method = $hook . c::classBasename($trait);

                    if (method_exists($component, $method)) {
                        $this->componentIdMethodMap[$component->id][$hook][] = [$component, $method];
                    }
                }
            }

            $methods = carr::get($this->componentIdMethodMap, $component->id . '.hydrate', []);

            foreach ($methods as $method) {
                CComponent_ImplicitlyBoundMethod::call(CContainer::getInstance(), $method);
            }
        });

        CComponent_Manager::instance()->listen('component.mount', function ($component, $params) {
            $methods = carr::get($this->componentIdMethodMap, $component->id . '.mount', []);

            foreach ($methods as $method) {
                CComponent_ImplicitlyBoundMethod::call(CContainer::getInstance(), $method, $params);
            }
        });

        CComponent_Manager::instance()->listen('component.updating', function ($component, $name, $value) {
            $methods = carr::get($this->componentIdMethodMap, $component->id . '.updating', []);

            foreach ($methods as $method) {
                CComponent_ImplicitlyBoundMethod::call(CContainer::getInstance(), $method, [$name, $value]);
            }
        });

        CComponent_Manager::instance()->listen('component.updated', function ($component, $name, $value) {
            $methods = carr::get($this->componentIdMethodMap, $component->id . '.updated', []);

            foreach ($methods as $method) {
                CComponent_ImplicitlyBoundMethod::call(CContainer::getInstance(), $method, [$name, $value]);
            }
        });

        CComponent_Manager::instance()->listen('component.rendering', function ($component) {
            $methods = carr::get($this->componentIdMethodMap, $component->id . '.rendering', []);

            foreach ($methods as $method) {
                CComponent_ImplicitlyBoundMethod::call(CContainer::getInstance(), $method);
            }
        });

        CComponent_Manager::instance()->listen('component.rendered', function ($component, $view) {
            $methods = carr::get($this->componentIdMethodMap, $component->id . '.rendered', []);

            foreach ($methods as $method) {
                CComponent_ImplicitlyBoundMethod::call(CContainer::getInstance(), $method, [$view]);
            }
        });

        CComponent_Manager::instance()->listen('component.dehydrate', function ($component) {
            $methods = carr::get($this->componentIdMethodMap, $component->id . '.dehydrate', []);

            foreach ($methods as $method) {
                CComponent_ImplicitlyBoundMethod::call(CContainer::getInstance(), $method);
            }
        });
    }
}
