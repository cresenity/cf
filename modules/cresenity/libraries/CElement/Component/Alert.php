<?php

class CElement_Component_Alert extends CElement_Component {
    use CTrait_Element_Property_Title;

    protected $header;

    protected $content;

    protected $type;

    public function __construct($id = '', $tag = 'div') {
        parent::__construct($id, $tag);
        $this->header = $this->addH4();
        $this->content = $this->add_div()->addClass(' clearfix');
        $this->addClass('alert');
        $this->wrapper = $this->content;
        $this->tag = 'div';
    }

    public function setType($type) {
        $this->type = $type;
        return $this;
    }

    public function build() {
        if (strlen($this->title) == 0) {
            $this->header->setVisibility(false);
        }
        $this->header->add($this->getTranslationTitle());
        switch ($this->type) {
            case 'error':
                $this->addClass('alert-danger');
                break;
            case 'info':
                $this->addClass('alert-info');
                break;
            case 'warning':
                $this->addClass('alert-warning');
                break;
            default:
                $this->addClass('alert-success');
                break;
        }
    }
}
