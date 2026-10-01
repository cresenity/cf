<?php

class CEmail_Builder_Component_HeadComponent_Font extends CEmail_Builder_Component_HeadComponent {
    protected static $tagName = 'c-font';

    protected $allowedAttributes = [
        'name' => 'string',
        'href' => 'string',
    ];

    public function handler() {
        $name = $this->getAttribute('name');
        $href = $this->getAttribute('href');
        if (strlen((string) $name) > 0 && strlen((string) $href) > 0) {
            $this->context->addFont($name, $href);
        }
    }
}
