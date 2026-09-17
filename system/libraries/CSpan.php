<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @deprecated since 1.2
 */
//@codingStandardsIgnoreStart
class CSpan extends CElement_Element {
    protected $col;

    protected $size;

    public function __construct($id = '') {
        parent::__construct($id);

        $this->size = 'md';
        $this->col = 12;
    }

    public static function factory($id = '') {
        return new CSpan($id);
    }

    public function set_col($col) {
        $this->col = $col;

        return $this;
    }

    public function set_size($size) {
        $this->size = $size;

        return $this;
    }

    public function html($indent = 0) {
        $html = new CStringBuilder();
        $html->setIndent($indent);
        $disabled = '';

        $html->appendln('<div class="span' . $this->col . '">');

        $html->appendln($this->htmlChild($html->getIndent()))->br();
        $html->appendln('</div>');

        return $html->text();
    }
}
