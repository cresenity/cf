<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @deprecated 1.2
 */
class CRowFluid extends CElement_Element {
    public function __construct($id) {
        parent::__construct($id);
    }

    public static function factory($id) {
        CDebug::collector()->collectDeprecated('CRowFluid is deprecated');

        return new CRowFluid($id);
    }

    public function html($indent = 0) {
        $html = new CStringBuilder();
        $html->setIndent($indent);
        $disabled = '';
        $html->appendln('<div id="' . $this->id . '" class="row-fluid">');

        $html->appendln($this->html_child())->br();
        $html->appendln('</div>');

        return $html->text();
    }
}
