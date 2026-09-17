<?php

/**
 * @deprecated since 1.2
 */
//@codingStandardsIgnoreStart
class CTabStatic extends CElement {
    protected $title;

    protected $active;

    public function __construct($id) {
        parent::__construct($id);

        $this->title = '';
        $this->active = false;
    }

    public static function factory($id) {
        return new CTabStatic($id);
    }

    public function set_active($bool) {
        $this->active = $bool;
        return $this;
    }

    public function set_title($title) {
        $this->title = $title;
        return $this;
    }

    public function header_html($indent = 0) {
        $class_active = '';
        if ($this->active) {
            $class_active = 'active';
        }
        return '<li class="' . $class_active . '"><a href="#' . $this->id . '" data-toggle="tab">' . $this->title . '</a></li>';
    }

    public function html($indent = 0) {
        $html = new CStringBuilder();
        $html->set_indent($indent);
        $add_class = '';
        $class_active = '';
        if ($this->active) {
            $class_active = 'active';
        }
        $html->appendln('<div class="tab-pane ' . $class_active . '" id="' . $this->id . '">');
        $html->appendln('<div class="tab-container ">');
        $html->appendln(parent::html($html->get_indent()));
        $html->appendln('</div>');
        $html->appendln('</div>');
        return $html->text();
    }

    public function js($indent = 0) {
        $js = new CStringBuilder();
        $js->set_indent($indent);

        $js->appendln(parent::js($indent));

        return $js->text();
    }
}
