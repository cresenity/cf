<?php

/**
 * @see CElement_Element_Div
 * @see CElement_FormInput_Select
 * @see CElement_FormInput_SelectSearch
 */
class CElement_Depends_DependsOn {
    use CTrait_HasOptions;

    /**
     * @var CElement_Depends_Selector
     */
    protected $selector;

    /**
     * @var CFunction_SerializableClosure
     */
    protected $resolver;

    /**
     * @param CRenderable|string|array $selector
     * @param callable                 $resolver
     * @param array                    $options
     *
     * @return void
     */
    public function __construct($selector, $resolver, $options = []) {
        $this->options = $options;

        $this->setResolver($resolver);
        $this->selector = new CElement_Depends_Selector(carr::wrap($selector));
    }

    /**
     * @param CRenderable|string|array $selector
     *
     * @return $this
     */
    public function addSelector($selector) {
        $this->selector->addSelector($selector);

        return $this;
    }

    /**
     * @param CRenderable|string|array $selector
     *
     * @return $this
     */
    public function setSelector($selector) {
        $this->selector->setSelectors($selector);

        return $this;
    }

    /**
     * @param callable $resolver
     *
     * @return $this
     */
    public function setResolver($resolver) {
        $this->resolver = c::toSerializableClosure($resolver);

        return $this;
    }

    /**
     * @return CElement_Depends_Selector
     */
    public function getSelector() {
        return $this->selector;
    }

    /**
     * @return CFunction_SerializableClosure
     */
    public function getResolver() {
        return $this->resolver;
    }

    /**
     * @return int
     */
    public function getThrottle() {
        return $this->getOption('throttle', 100);
    }

    /**
     * @return bool
     */
    public function getBlock() {
        return $this->getOption('block', true);
    }
}
