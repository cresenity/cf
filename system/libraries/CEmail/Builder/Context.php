<?php

class CEmail_Builder_Context {
    protected $data = [];

    public function __construct($initialData = []) {
        $this->data = $initialData;
    }

    public function setBackgroundColor($color) {
        $globalData = CEmail::builder()->globalData();
        $globalData->set('backgroundColor', $color);

        return $this;
    }

    public function addHeadStyle($identifier, $headStyle) {
        $globalData = CEmail::builder()->globalData();
        $globalData->set('headStyle.' . $identifier, $headStyle);
    }

    public function addComponentHeadStyle($identifier, $headStyle = null) {
        if ($headStyle === null) {
            $headStyle = $identifier;
            $identifier = null;
        }
        $globalData = CEmail::builder()->globalData();
        if ($identifier === null) {
            $globalData->push('componentHeadStyle', [$headStyle]);
        } else {
            $globalData->set('componentHeadStyle.' . $identifier, $headStyle);
        }
    }

    public function getBackgroundColor($color = null) {
        $globalData = CEmail::builder()->globalData();

        return $globalData->get('backgroundColor');
    }

    public function getContainerWidth() {
        return $this->get('containerWidth');
    }

    public function get($key, $defaultValue = null) {
        return carr::get($this->data, $key, $defaultValue);
    }

    public function set($key, $value) {
        return carr::set($this->data, $key, $value);
    }

    public function data() {
        return $this->data;
    }

    public function addMediaQuery($className, $options) {
        $parsedWidth = carr::get($options, 'parsedWidth');
        $unit = carr::get($options, 'unit');
        $globalData = CEmail::builder()->globalData();

        $globalData->set('mediaQueries.' . $className, '{ width:' . $parsedWidth . $unit . ' !important; max-width:' . $parsedWidth . $unit . '; }');
    }

    public function addHead() {
        $args = func_get_args();
        $attr = carr::get($args, 0);
        $params = [];
        if (count($args) > 1) {
            $params = array_slice($args, 1);
        }
        $globalData = CEmail::builder()->globalData();
        $attrToPush = ['inlineStyle', 'componentsHeadStyle', 'headRaw', 'style'];
        if (in_array($attr, $attrToPush)) {
            $globalData->push($attr, $params);
        } elseif ($globalData->exists($attr)) {
            if (count($params) > 1) {
                $paramKey = carr::get($params, 0);
                $attrKey = $attr . '.' . $paramKey;
                if (is_object($globalData->get($attrKey))) {
                    throw new Exception('unimplement');
                } else {
                    $globalData->set($attrKey, carr::get($params, 1));
                }
            } else {
                $globalData->set($attr, carr::get($params, 0));
            }
        } else {
            throw new Exception('head element add an unknown head attribute : ' . $attr . '');
        }
    }
}
