<?php

/**
 * @deprecated since 1.2, dont use this anymore
 */
//@codingStandardsIgnoreStart
class CFormInputModal extends CFormInput {
    protected $title;

    protected $footer;

    public function __construct($id = '') {
        parent::__construct($id);

        $this->title = false;
        $this->footer = false;
    }

    public static function factory($id = '') {
        return new CFormInputModal($id);
    }

    public function html($indent = 0) {
        $html = new CStringBuilder();

        $classes = $this->classes;
        $classes = implode(' ', $classes);
        if (strlen($classes) > 0) {
            $classes = ' ' . $classes;
        }

        $html->appendln('<div class="modal fade ' . $classes . '" tabindex="-1" id="' . $this->id . '" role="dialog">');
        $html->appendln('   <div class="modal-dialog" role="document">');
        $html->appendln('       <div class="modal-content">');
        $html->appendln('           <div class="modal-header">');
        $html->appendln('               <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>');
        if ($this->title === false) {
            // no title
        } else {
            $html->appendln('               <h4 class="modal-title">' . $this->title . '</h4>');
        }
        $html->appendln('           </div>'); // close div modal-header
        $html->appendln('           <div class="modal-body">');
        $html->appendln(parent::html($indent));
        $html->appendln('           </div>'); // close div modal-header
        if ($this->footer === false) {
            // no footer
        } else {
            $html->appendln('       <div class="modal-footer">');
            if ($this->footer instanceof CRenderable) {
                $html->appendln($this->footer->html());
            } else {
                $html->appendln($this->footer);
            }
            $html->appendln('       </div>'); // close div modal-header
        }
        $html->appendln('       </div>'); // close div modal-content
        $html->appendln('   </div>'); //close div modal-dialog
        $html->appendln('</div>'); // close div modal

        return $html->text();
    }

    public function js($indent = 0) {
        $js = new CStringBuilder();
        if ($this->footer instanceof CRenderable) {
            $js->appendln($this->footer->js());
        }
        $js->appendln(parent::js($indent));

        return $js->text();
    }

    public function get_title() {
        return $this->title;
    }

    public function get_footer() {
        return $this->footer;
    }

    public function set_title($title) {
        $this->title = $title;

        return $this;
    }

    public function add_footer($id = '') {
        $this->footer = c::div($id);

        return $this->footer;
    }
}
