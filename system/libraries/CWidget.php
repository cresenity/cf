<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @see CElement_Component_Widget
 * @deprecated since 1.2, use CElement_Component_Widget
 */
// @codingStandardsIgnoreStart
class CWidget extends CElement_Element {
    public $title;

    public $content;

    public $span;

    public $scroll;

    public $icon;

    public $wrapped;

    public $nopadding;

    public $info;

    public $info_tip;

    public $custom_html;

    public $header_action_style;

    public $height;

    public $attr;

    protected $header_action_list;

    /**
     * Undocumented variable.
     *
     * @var CFormInput
     */
    protected $switcher;

    private $collapse;

    private $close;

    private $js_collapse;

    public function __construct($id) {
        parent::__construct($id);

        $this->icon = 'th-list';
        $this->title = '';
        $this->height = '';
        $this->content = '';
        $this->span = 12;
        $this->wrapped = true;
        $this->scroll = false;
        $this->info = '';
        $this->info_tip = '';
        $this->custom_html = '';
        $this->nopadding = false;
        $this->header_action_list = CActionList::factory();
        $this->header_action_style = 'widget-action';
        $this->header_action_list->setStyle('widget-action');
        $this->switcher = null;
        $this->attr = [];
        $this->collapse = false;
        $this->close = false;
        $this->js_collapse = true;
    }

    public static function factory($id = '') {
        return new CWidget($id);
    }

    public function have_header_action() {
        //return $this->can_edit||$this->can_delete||$this->can_view;
        return $this->header_action_list->child_count() > 0;
    }

    public function add_header_action($id = '') {
        $row_act = CAction::factory($id);

        $this->header_action_list->add($row_act);

        return $row_act;
    }

    public function add_header_action_list($id = '') {
        $row_acts = CActionList::factory($id);
        $row_acts->set_style('btn-dropdown');
        $this->header_action_list->add($row_acts);

        return $row_acts;
    }

    public function set_header_action_style($style) {
        $this->header_action_style = $style;
        $this->header_action_list->set_style($style);
    }

    public function have_switcher() {
        if ($this->switcher) {
            return true;
        } else {
            return false;
        }
    }

    public function add_switcher($id = '') {
        return $this->switcher = CFactory::create_control($id, 'switcher');
    }

    public function set_title($title, $lang = true) {
        if ($lang) {
            $title = clang::__($title);
        }
        $this->title = $title;

        return $this;
    }

    public function set_height($height) {
        $this->height = $height;

        return $this;
    }

    public function set_icon($icon) {
        $this->icon = $icon;

        return $this;
    }

    public function set_scroll($bool) {
        $this->scroll = $bool;

        return $this;
    }

    public function set_nopadding($bool) {
        $this->nopadding = $bool;

        return $this;
    }

    public function set_wrapped($bool) {
        $this->wrapped = $bool;

        return $this;
    }

    public function set_info($info) {
        $this->info = $info;

        return $this;
    }

    public function add_html($custom_html) {
        $this->custom_html = $custom_html;

        return $this;
    }

    public function set_info_tip($info_tip) {
        $this->info_tip = $info_tip;

        return $this;
    }

    public function add_content_attr($k, $v) {
        $this->attr[$k] = $v;

        return $this;
    }

    public function get_collapse() {
        return $this->collapse;
    }

    public function get_close() {
        return $this->close;
    }

    public function set_collapse($collapse, $js_collapse = false) {
        $this->collapse = $collapse;
        $this->js_collapse = $js_collapse;

        return $this;
    }

    public function set_close($close) {
        $this->close = $close;

        return $this;
    }

    public function html($indent = 0) {
        $html = new CStringBuilder();
        $html->set_indent($indent);
        $disabled = '';

        $main_class = ' widget-box ';
        $main_class_title = ' widget-title ';
        $main_class_content = ' widget-content ';
        $class_title = '';

        if ($this->header_action_style == 'btn-dropdown') {
            $this->header_action_list->add_class('pull-right');
        }
        if ($this->wrapped) {
            $html->appendln('<div class="row-fluid">
				<div class="span' . $this->span . '">');
        }
        $nopadding = '';
        if ($this->nopadding) {
            $nopadding = 'nopadding';
        }
        $info = '';
        if (strlen($this->info) > 0) {
            $info = '<span class="label label-info tip-left " data-original-title="' . $this->info_tip . '">' . $this->info . '</span>';
        }
        $custom_html = '';
        if (strlen($this->custom_html) > 0) {
            $custom_html = $this->custom_html;
        }
        $classes = $this->classes;
        $classes = implode(' ', $classes);
        if (strlen($classes) > 0) {
            $classes = ' ' . $classes;
        }
        $custom_css = $this->custom_css;
        $custom_css = $this->renderStyle($custom_css);
        $html->appendln('<div id="' . $this->id . '" class="' . $main_class . $classes . '" style="' . $custom_css . '">');
        $html->appendln('	<div class="' . $main_class_title . '">');

        $html->appendln('       <span class="icon">');

        $html->appendln('		<i class="icon-' . $this->icon . '"></i>');

        $html->appendln('		</span>');

        $html->appendln('       <h5>' . $this->title . '</h5>');

        $html->appendln('		' . $custom_html . '');
        $html->appendln('		' . $info . '');
        if ($this->have_header_action()) {
            $html->appendln($this->header_action_list->html($html->get_indent()));
        }

        if ($this->have_switcher()) {
            $html->appendln('<div class="pull-right">');
            $html->appendln($this->switcher->html());
            $html->appendln('</div>');
        }

        $scroll_class = '';
        if ($this->scroll) {
            $scroll_class = ' slimscroll';
        }
        $str_height = '';
        if (strlen($this->height) > 0) {
            $str_height = ' height="' . $this->height . 'px"';
        }

//        cdbg::var_dump($this->attr);
        $content_attr = '';
        if (count($this->attr) > 0) {
            foreach ($this->attr as $attr_k => $attr_v) {
                $content_attr .= $attr_k . '="' . $attr_v . '" ';
            }
        }

        $html->appendln('	</div>');
        $html->appendln('	<div class="clearfix ' . $main_class_content . $nopadding . $scroll_class . '"' . $str_height . $content_attr . '>');
        $html->appendln('		' . $this->html_child() . '');
        $html->appendln('	</div>');
        $html->appendln('</div>');
        $html->br();

        if ($this->wrapped) {
            $html->appendln('</div>
			</div>');
        }

        return $html->text();
    }

    public function js($indent = 0) {
        $js = new CStringBuilder();
        $js->set_indent($indent);
        if ($this->have_header_action()) {
            $js->appendln($this->header_action_list->js($js->get_indent()));
        }

        if ($this->have_switcher()) {
            $js->appendln('
                if (jQuery("#' . $this->switcher->getFieldId() . '").prop("checked")) {
                    jQuery("#' . $this->id . '").find(".widget-content").show();
                } else {
                    jQuery("#' . $this->id . '").find(".widget-content").hide();
                }

                jQuery("#' . $this->switcher->getFieldId() . '").click(function() {
                    if (jQuery("#' . $this->switcher->getFieldId() . '").prop("checked")) {
                        jQuery("#' . $this->id . '").find(".widget-content").show();
                    } else {
                        jQuery("#' . $this->id . '").find(".widget-content").hide();
                    }
                })
            ');
        }

        $js->append($this->jsChild($js->getIndent()));

        return $js->text();
    }
}
