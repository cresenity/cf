<?php

use CEmail_Builder_Helper as Helper;

class CEmail_Builder_Component_BodyComponent extends CEmail_Builder_Component {
    public function getStyles() {
        return [];
    }

    public function getShorthandAttrValue($attribute, $direction) {
        $componentAttributeDirection = $this->getAttribute($attribute . '-' . $direction);
        $componentAttribute = $this->getAttribute($attribute);

        if ($componentAttributeDirection) {
            return $componentAttributeDirection;
        }

        if (!$componentAttribute) {
            return 0;
        }

        return Helper::shorthandParser($componentAttribute, $direction);
    }

    public function getShorthandBorderValue($direction) {
        $borderDirection = $direction ? $this->getAttribute('border-' . $direction) : null;
        $border = $this->getAttribute('border');

        return Helper::borderParser($borderDirection ?: $border ?: '0');
    }

    public function htmlAttributes($attributes) {
        return carr::reduce($attributes, function ($output, $v, $name) {
            $value = $v;
            if ($name == 'style') {
                if (is_string($value)) {
                    $value = carr::get($this->getStyles(), $v);
                }
                $value = Helper::renderStyle($value);
            }

            if ($value != null && strlen($value) > 0) {
                if (($name === 'width' || $name === 'height') && preg_match('/^\d+(\.\d+)?px$/', $value)) {
                    // atribut HTML width/height tanpa satuan; satuan tetap ada di style
                    $value = substr($value, 0, -2);
                }

                return $output . ' ' . $name . '="' . htmlspecialchars($value, ENT_COMPAT | ENT_HTML401, 'UTF-8', false) . '"';
            }

            return $output;
        }, '');
    }

    public function getBoxWidths() {
        $containerWidth = $this->context->getContainerWidth();
        //$parsedWidth = (int) $containerWidth;

        $widthParserResult = Helper::widthParser($containerWidth, ['parseFloatToInt' => false]);
        $unit = carr::get($widthParserResult, 'unit');
        $parsedWidth = carr::get($widthParserResult, 'parsedWidth');
        $paddings = intval($this->getShorthandAttrValue('padding', 'right')) + intval($this->getShorthandAttrValue('padding', 'left'));
        $borders = intval($this->getShorthandBorderValue('right')) + intval($this->getShorthandBorderValue('left'));

        return [
            'totalWidth' => $parsedWidth,
            'borders' => $borders,
            'paddings' => $paddings,
            'box' => $parsedWidth - $paddings - $borders,
        ];
    }

    public function styles($styles) {
        $stylesArray = null;

        if ($styles != null) {
            if (is_string($styles)) {
                $stylesArray = carr::get($this->getStyles(), $styles);
            } else {
                $stylesArray = $styles;
            }
        }

        return carr::reduce($stylesArray, function ($output, $value, $name) {
            if ($value != null) {
                return $output . $name . ':' . $value;
            }

            return $output;
        }, '');
    }

    public function renderChildren($options = []) {
        $childrens = $this->getChildren();
        if ($childrens == null) {
            return '';
        }

        $renderer = function ($component) {
            if (!method_exists($component, 'render')) {
            }

            return $component->render();
        };
        $rawXML = carr::get($options, 'rawXML', false);
        $attributes = carr::get($options, 'attributes', []);
        if (isset($options['renderer'])) {
            $renderer = $options['renderer'];
        }

        $props = carr::get($options, 'props', []);

        if ($rawXML) {
            return carr::reduce($childrens, function ($output, $child) {
                return $output .= "\n" . Helper::jsonToXML($child->getTagName(), $child->getAttributes(), $child->getChildren(), $child->getContent());
            }, '');
        }
        $sibling = count($childrens);
        $rawComponents = carr::filter(CEmail::builder()->components(), function ($c) {
            return $c::isRawElement();
        });

        $nonRawSiblings = count(carr::filter($childrens, function ($child) use ($rawComponents) {
            return !carr::find($rawComponents, function ($c) use ($child) {
                return $c::getTagName() == $child->getTagName();
            });
        }));

        $output = '';
        $index = 0;
        foreach ($childrens as $children) {
            $component = $children;
            if ($children instanceof CEmail_Builder_Node) {
                $globalData = CEmail::builder()->globalData();
                $globalAttributes = array_merge(
                    $globalData->get('defaultAttributes.c-all', []),
                    $globalData->get('defaultAttributes.' . $children->getTagName(), [])
                );
                $options = [];
                $options['children'] = $children->getChildren();
                $options['attributes'] = array_merge($attributes, $globalAttributes, $children->getAttributes());
                $options['context'] = $this->getChildContext();
                $options['name'] = $children->getComponentName();
                $options['content'] = $children->getContent();
                $options['props'] = [];
                $options['props']['first'] = $index === 0;
                $options['props']['index'] = $index;
                $options['props']['last'] = $sibling - 1 === $index;
                $options['props']['sibling'] = $sibling;
                $options['props']['nonRawSiblings'] = $nonRawSiblings;

                $component = CEmail::Builder()->createComponent($children->getComponentName(), $options);
            }
            if ($component != null) {
                $output .= $renderer($component);
            }
            $index++;
        }

        return $output;
    }
}
