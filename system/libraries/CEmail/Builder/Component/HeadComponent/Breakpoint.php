<?php

class CEmail_Builder_Component_HeadComponent_Breakpoint extends CEmail_Builder_Component_HeadComponent {
    protected static $tagName = 'c-breakpoint';

    protected $allowedAttributes = [
        'width' => 'unit(px)',
    ];

    public function handler() {
        $width = $this->getAttribute('width');
        if (strlen((string) $width) > 0) {
            $this->context->addHead('breakpoint', $width);
        }
    }
}
