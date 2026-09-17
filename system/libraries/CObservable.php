<?php

abstract class CObservable extends CRenderable {
    use CTrait_Compat_Observable,
        CObservable_Trait_ElementTrait,
        CObservable_Trait_ComponentTrait,
        CObservable_Trait_ListTrait,
        CObservable_Trait_EventsTrait,
        CObservable_Trait_ControlTrait,
        CObservable_Trait_ListenerTrait;

    /**
     * @var CObservable_Javascript
     */
    protected $javascript;

    protected function __construct($id = null) {
        parent::__construct($id);
        $this->listeners = [];
    }

    /**
     * @return CObservable_Javascript
     */
    public function javascript() {
        if ($this->javascript == null) {
            $this->javascript = new CObservable_Javascript($this);
        }

        return $this->javascript;
    }

    /**
     * @return CObservable_Javascript_JQuery
     */
    public function jquery() {
        return $this->javascript()->jquery();
    }

    /**
     * @return CObservable_Javascript_Handler
     */
    public function handler() {
        return $this->javascript()->handler();
    }

    /**
     * @param string|CElement_FormInput $id
     * @param string                    $type
     *
     * @return CElement_FormInput
     */
    public function addControl($id, $type = 'text') {
        $control = null;
        if ($id instanceof CElement_FormInput) {
            $control = $id;
        }
        if ($control == null) {
            $control = CManager::instance()->createControl($id, $type);
        }

        $this->wrapper->add($control);

        return $control;
    }

    /**
     * @deprecated use addView() instead
     *
     * @param string $id
     *
     * @return CElement_Template
     */
    public function addTemplate($id = '') {
        $template = CElement_Factory::createTemplate($id);
        $this->wrapper->add($template);

        return $template;
    }

    /**
     * @param CElement_View|string $view
     * @param null|array           $data
     * @param string               $id
     *
     * @return CElement_View
     */
    public function addView($view = null, $data = null, $id = null) {
        if (strlen($id) == 0) {
            $id = 'view-' . cstr::slug($view) . '-' . CObserver::instance()->newId();
        }

        $viewElement = CElement_Factory::createView($id, $view, $data);

        $this->wrapper->add($viewElement);

        return $viewElement;
    }

    /**
     * Add Form Field.
     *
     * @param string $id
     *
     * @return CElement_Component_Form_Field Form Field
     */
    public function addField($id = '') {
        $field = new CElement_Component_Form_Field($id);
        $this->wrapper->add($field);

        return $field;
    }

    public function addPdfViewer($id = '') {
        $code = CElement_Factory::createComponent('PdfViewer', $id);
        $this->add($code);

        return $code;
    }

    /**
     * @return $this
     */
    public function addHr() {
        $this->add('<hr />');

        return $this;
    }

    /**
     * @return $this
     */
    public function addBr() {
        $this->add('<br />');

        return $this;
    }

    /**
     * @param string $type
     * @param string $id
     *
     * @return CElement
     */
    public function addElement($type, $id = '') {
        $element = null;
        if (CManager::instance()->isRegisteredElement($type)) {
            $element = CManager::instance()->createElement($id, $type);
        } else {
            throw new Exception(c::__('Unknown element type :elementType', ['elementType' => $type]));
        }

        $this->add($element);

        return $element;
    }

    /**
     * @param string $id
     *
     * @return CElement_Component_Accordion
     */
    public function addAccordion($id = '') {
        $element = CElement_Factory::createComponent('Accordion', $id);
        $this->add($element);

        return $element;
    }

    /**
     * @param string $id
     *
     * @return CElement_Component_Icon
     */
    public function addIcon($id = '') {
        $icon = CElement_Factory::createComponent('Icon', $id);
        $this->add($icon);

        return $icon;
    }

    public function clearBoth() {
        $this->add('<div class="clear-both"></div>');
    }

    /**
     * @param string $param
     *
     * @return void
     */
    public function setHandlerParam($param) {
        foreach ($this->listeners as $listener) {
            $listener->setHandlerParam($param);
        }
    }

    public function js($indent = 0) {
        $js = new CStringBuilder();
        $js->setIndent($indent);
        foreach ($this->listeners as $listener) {
            $js->appendln($listener->js($js->getIndent()));
        }

        $js->appendln(parent::js($js->getIndent()))->br();

        return $js->text();
    }

    public function regenerateId($recursive = false) {
        $beforeId = $this->id;

        parent::regenerateId($recursive);
        //we change the owner of listener
        foreach ($this->listeners as $listener) {
            if ($listener->owner() == $beforeId) {
                $listener->setOwner($this->id);
            }
        }
    }
}
