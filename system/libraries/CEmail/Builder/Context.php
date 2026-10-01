<?php

class CEmail_Builder_Context {
    protected $data = [];

    /**
     * @var null|CEmail_Builder_GlobalData
     */
    protected $globalData;

    /**
     * @param array                          $initialData
     * @param null|CEmail_Builder_GlobalData $globalData
     */
    public function __construct($initialData = [], CEmail_Builder_GlobalData $globalData = null) {
        $this->data = $initialData;
        $this->globalData = $globalData;
    }

    /**
     * Data global milik render ini; tanpa data eksplisit memakai yang sedang aktif.
     *
     * @return CEmail_Builder_GlobalData
     */
    public function globalData() {
        return $this->globalData ?: CEmail::builder()->globalData();
    }

    public function setBackgroundColor($color) {
        $globalData = $this->globalData();
        $globalData->set('backgroundColor', $color);

        return $this;
    }

    public function addHeadStyle($identifier, $headStyle) {
        $globalData = $this->globalData();
        $globalData->set('headStyle.' . $identifier, $headStyle);
    }

    /**
     * @param string $name
     * @param string $href
     *
     * @return $this
     */
    public function addFont($name, $href) {
        $globalData = $this->globalData();
        $fonts = $globalData->get('fonts', []);
        $fonts[$name] = $href;
        $globalData->set('fonts', $fonts);

        return $this;
    }

    public function addComponentHeadStyle($identifier, $headStyle = null) {
        if ($headStyle === null) {
            $headStyle = $identifier;
            $identifier = null;
        }
        $globalData = $this->globalData();
        if ($identifier === null) {
            $globalData->push('componentHeadStyle', [$headStyle]);
        } else {
            $globalData->set('componentHeadStyle.' . $identifier, $headStyle);
        }
    }

    public function getBackgroundColor($color = null) {
        $globalData = $this->globalData();

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
        $globalData = $this->globalData();

        $globalData->set('mediaQueries.' . $className, '{ width:' . $parsedWidth . $unit . ' !important; max-width:' . $parsedWidth . $unit . '; }');
    }

    public function addHead() {
        $args = func_get_args();
        $attr = carr::get($args, 0);
        $params = [];
        if (count($args) > 1) {
            $params = array_slice($args, 1);
        }
        $globalData = $this->globalData();
        if ($attr === 'componentsHeadStyle') {
            $attr = 'componentHeadStyle';
        }
        $attrToPush = ['inlineStyle', 'componentHeadStyle', 'headRaw', 'style'];
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
