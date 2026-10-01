<?php

class CEmail_Builder_Component_HeadComponent_Preview extends CEmail_Builder_Component_HeadComponent {
    protected static $tagName = 'c-preview';

    protected static $endingTag = true;

    public function handler() {
        $this->context->addHead('preview', htmlspecialchars($this->getContent(), ENT_QUOTES, 'UTF-8', false));
    }
}
