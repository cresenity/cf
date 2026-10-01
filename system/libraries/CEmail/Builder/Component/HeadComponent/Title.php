<?php

class CEmail_Builder_Component_HeadComponent_Title extends CEmail_Builder_Component_HeadComponent {
    protected static $tagName = 'c-title';

    protected static $endingTag = true;

    public function handler() {
        $this->context->addHead('title', htmlspecialchars($this->getContent(), ENT_QUOTES, 'UTF-8', false));
    }
}
