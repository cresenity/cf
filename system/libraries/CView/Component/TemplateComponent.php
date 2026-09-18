<?php

class CView_Component_TemplateComponent extends CView_ComponentAbstract {
    protected $template;

    public function __construct($template) {
        $this->template = $template;
    }

    public function render() {
        return $this->template;
    }
}
