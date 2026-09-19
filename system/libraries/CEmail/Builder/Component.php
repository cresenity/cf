<?php

class CEmail_Builder_Component {
    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $props = [];

    /**
     * @var CEmail_Builder_Context
     */
    protected $context = null;

    /**
     * @var array
     */
    protected $defaultAttributes = [];

    /**
     * @var array
     */
    protected $allowedAttributes = [];

    /**
     * @var array
     */
    protected $headStyle = [];

    /**
     * @var array
     */
    protected $componentHeadStyle = [];

    /**
     * @var array
     */
    protected $children = [];

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * @var string
     */
    protected $content = '';

    /**
     * @var bool
     */
    protected static $rawElement = false;

    /**
     * @var bool
     */
    protected static $endingTag = false;

    /**
     * @var string
     */
    protected static $tagName = '';

    /**
     * @param array $options
     */
    public function __construct($options) {
        $defaultOptions = [];
        $defaultOptions['attributes'] = [];
        $defaultOptions['children'] = [];
        $defaultOptions['content'] = '';
        $defaultOptions['context'] = [];
        $defaultOptions['props'] = [];
        $defaultOptions['globalAttributes'] = [];
        $options = array_merge($defaultOptions, $options);

        $this->props = carr::get($options, 'props');
        $this->children = carr::get($options, 'children', []);
        $this->content = carr::get($options, 'content', '');
        $this->name = carr::get($options, 'name');

        $globalData = CEmail::builder()->globalData();
        $globalAttributes = array_merge(
            $globalData->get('defaultAttributes.c-all', []),
            $globalData->get('defaultAttributes.' . static::$tagName, [])
        );

        //$attributes = array_merge($this->defaultAttributes, carr::get($options, 'globalAttributes', []), carr::get($options, 'attributes', []));
        $attributes = array_merge($this->defaultAttributes, $globalAttributes, carr::get($options, 'attributes', []));
        $this->attributes = CEmail_Builder_Helper::formatAttributes($attributes, $this->allowedAttributes);
        $this->context = carr::get($options, 'context');
    }

    /**
     * @return string
     */
    public static function getTagName() {
        return static::$tagName;
    }

    /**
     * @return bool
     */
    public static function isEndingTag() {
        return static::$endingTag;
    }

    /**
     * @return bool
     */
    public static function isRawElement() {
        return !!static::$rawElement;
    }

    /**
     * @return null|CEmail_Builder_Context
     */
    public function getChildContext() {
        return $this->context;
    }

    public function add($element) {
        $rawElement = $element;
        if ($rawElement instanceof CEmail_Builder_Component) {
            $this->children[] = $rawElement;
        } else {
            $rawElement = new CEmail_Builder_Component_BodyComponent_Raw([]);
            $rawElement->setContent($element);
            $this->children[] = $rawElement;
        }

        return $rawElement;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public function getAttribute($name) {
        return carr::get($this->attributes, $name);
    }

    /**
     * @return string
     */
    public function getContent() {
        return trim($this->content);
    }

    /**
     * @param string $content
     *
     * @return $this
     */
    public function setContent($content) {
        $this->content = trim($content);

        return $this;
    }

    /**
     * @return CEmail_Builder_Component_BodyComponent_Body
     */
    public function addBody() {
        $element = new CEmail_Builder_Component_BodyComponent_Body([]);
        $this->add($element);

        return $element;
    }

    /**
     * @return CEmail_Builder_Component_BodyComponent_Section
     */
    public function addSection() {
        $element = new CEmail_Builder_Component_BodyComponent_Section([]);
        $this->add($element);

        return $element;
    }

    /**
     * @return CEmail_Builder_Component_BodyComponent_Column
     */
    public function addColumn() {
        $element = new CEmail_Builder_Component_BodyComponent_Column([]);
        $this->add($element);

        return $element;
    }

    /**
     * @return CEmail_Builder_Component_BodyComponent_Image
     */
    public function addImage() {
        $element = new CEmail_Builder_Component_BodyComponent_Image([]);
        $this->add($element);

        return $element;
    }

    public function setAttr($key, $value) {
        $this->attributes[$key] = $value;

        return $this;
    }

    public function getHeadStyle() {
        return $this->headStyle;
    }

    public function getComponentHeadStyle() {
        return $this->componentHeadStyle;
    }

    public function hasHeadStyle() {
        return count($this->headStyle) > 0;
    }

    public function hasComponentHeadStyle() {
        return count($this->componentHeadStyle) > 0;
    }

    public function getProp($key, $defaultValue = null) {
        return carr::get($this->props, $key, $defaultValue);
    }

    public function getChildren() {
        return $this->children;
    }
}
