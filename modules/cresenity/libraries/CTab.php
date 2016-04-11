<?php

    class CTab extends CElement {

        protected $label;
        protected $active;
        protected $icon;
        protected $target;
        protected $ajax_url;
        protected $ajax;
        protected $nopadding;

        public function __construct($id = "") {
            parent::__construct($id);
            $this->add_friend('CTabList');
            $this->label = "";
            $this->target = "";
            $this->icon = "";
            $this->ajax_url = "";
            $this->ajax = true;
            $this->active = false;
            $this->nopadding = false;
        }

        public static function factory($id = "") {
            return new CTab($id);
        }

        public function set_active($bool) {
            $this->active = $bool;
            return $this;
        }

        public function set_nopadding($bool) {
            $this->nopadding = $bool;
            return $this;
        }

        public function set_label($label, $lang = true) {
            if ($lang) $label = clang::__($label);
            $this->label = $label;
            return $this;
        }

        public function set_icon($icon) {
            $this->icon = $icon;
            return $this;
        }

        public function set_target($target) {
            $this->target = $target;
            return $this;
        }

        public function set_ajax_url($url) {
            $this->ajax_url = $url;
            return $this;
        }

        public function set_ajax($bool) {
            $this->ajax = $bool;
            return $this;
        }

        public function header_html($indent = 0) {

            if (strlen($this->ajax_url) == 0) {
                if ($this->ajax) {
                    $ajax_url = CAjaxMethod::factory()->set_type('reload')
                            ->set_data('json', $this->json())
                            ->makeurl();
                    $this->set_ajax_url($ajax_url);
                }
            }


            $class_active = "";
            if ($this->active) {
                $class_active = "active";
            }
            $tab_icon = "";
            if (strlen($this->icon) > 0) {
                $tab_icon = ' data-icon="icon-' . $this->icon . '"';
            }

            $tab_class = "";


            $classes = "";

            if (count($this->classes) > 0) {
                $classes = implode(" ", $this->classes);
            }
            if ($this->nopadding) {
                $classes.=" nopadding";
            }
            if (strlen($classes) > 0) {
                $tab_class = ' data-class="' . $classes . '"';
            }
            
            $tab_responsive = "";
            $tab_tab = "";
            if (strlen($this->id) > 0) {
                $tab_tab = ' data-tab="' . $this->id . '"';
                $tab_responsive = ' tab-responsive="#' . $this->id . '"';
            }

            $tab_target = "";
            if (strlen($this->target) > 0) {
                $tab_target = ' data-target="' . $this->target . '"';
            }

            $tab_url = "";
            if (strlen($this->ajax_url) > 0) {
                $tab_url = ' data-url="' . $this->ajax_url . '"';
            }
            $tab_label = "";
            if (strlen($this->label) > 0) {
                $tab_label = $this->label;
            }

            $html = '<li class="' . $class_active . '"><a href="javascript:;" ' . $tab_class . $tab_icon . $tab_tab . $tab_target . $tab_responsive . $tab_url . ' class="tab-ajax-load">';
            $html .= $tab_label;
            $html .= '</a></li>';

            return $html;
        }

        public function responsive_header_html($indent = 0) {

            // if (strlen($this->ajax_url) == 0) {
            //     if ($this->ajax) {
            //         $ajax_url = CAjaxMethod::factory()->set_type('reload')
            //                 ->set_data('json', $this->json())
            //                 ->makeurl();
            //         $this->set_ajax_url($ajax_url);
            //     }
            // }

            $class_active = "";
            if ($this->active) {
                $class_active = "active";
            }
            
            $tab_class = "";
            
            $tab_url = "";
            if (strlen($this->ajax_url) > 0) {
                $tab_url = ' data-url="' . $this->ajax_url . '"';
                $tab_href = "#";
            }
            else {
                $tab_href = "#".$this->id;
                $tab_class .= "static-tab";
            }

            $tab_label = "";
            if (strlen($this->label) > 0) {
                $tab_label = $this->label;
            }

            $tab_target = "";
            if (strlen($this->target) > 0) {
                $tab_target = ' data-target="' . $this->target . '"';
            }

            $html = '<li class="' . $class_active . '"><a href="'.$tab_href.'" ' . ' class="'.$tab_class. '"' . $tab_target . $tab_url . ' data-toggle="tab">';
            $html .= $tab_label;
            if (strlen($this->ajax_url) > 0) {
                $html .= '</a><div id="'.$this->id.'" class="resp-tab-target"></div></li>';
            }
            else {
                $html .= '</a>
                    <div class="resp-tab-target-static">

                    </div>
                </li>';
            }

            return $html;
        }

        public function html($indent = 0) {

            $html = new CStringBuilder();
            $html->set_indent($indent);
            $add_class = "";
            $class_active = "";
            if ($this->active) {
                $class_active = "active";
            }
            $additional_style = "";
            if (strlen($this->ajax_url) > 0) {
                $additional_style.="display:none;";
            }
            $html->appendln('<div class="tab-pane ' . $class_active . '" id="' . $this->id . '" style="' . $additional_style . '">');
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

?>