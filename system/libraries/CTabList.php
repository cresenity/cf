<?php

/**
 * @see CElement_List_TabList
 * @deprecated 2.0
 */
//@codingStandardsIgnoreStart
class CTabList extends CElement_Element {
    use CTrait_Compat_Element_TabList;
    protected $tabs;

    protected $scrollspy;

    protected $tab_position;

    protected $active_tab;

    protected $ajax;

    protected $have_icon;

    protected $widget_class;

    public function __construct($id) {
        parent::__construct($id);

        $this->tab_position = 'left';
        $this->active_tab = '';
        $this->ajax = true;
        $this->have_icon = false;
        $this->scrollspy = true;
        $this->tabs = [];
        $this->widget_class = [];
    }

    public static function factory($id) {
        return new CTabList($id);
    }

    public function addTab($id = '') {
        $tab = CTab::factory($id);
        if (strlen($this->active_tab) == 0) {
            $this->active_tab = $tab->id;
        }
        $this->tabs[] = $tab;

        return $tab;
    }

    public function active_tab($tab_id) {
        $this->set_active_tab($tab_id);

        return $this;
    }

    public function set_active_tab($tab_id) {
        $this->active_tab = $tab_id;

        return $this;
    }

    public function set_scrollspy($bool) {
        $this->scrollspy = $bool;

        return $this;
    }

    public function set_ajax($bool) {
        $this->ajax = $bool;

        return $this;
    }

    public function set_tab_position($tab_position) {
        $this->tab_position = $tab_position;

        return $this;
    }

    public function add_widget_class($class) {
        if (is_array($class)) {
            $this->widget_class = array_merge($class, $this->widget_class);
        } else {
            $this->widget_class[] = $class;
        }

        return $this;
    }

    public function html($indent = 0) {
        /*
          $html = new CStringBuilder();
          $html->setIndent($indent);
          $add_class="";
          if($this->tab_position=="left") {
          $add_class.=" tabs-left";
          }
          $html->appendln('<div class="tabbable '.$add_class.'">');
          $html->appendln('<ul class="nav nav-tabs ">');
          foreach($this->tabs as $tab) {

          if($tab->id==$this->active_tab) {
          $tab->set_active(true);
          }
          $html->appendln($tab->header_html($html->get_indent()));
          }
          $html->appendln('</ul>');
          $html->appendln('<div class="tab-content">');
          foreach($this->tabs as $tab) {
          $html->appendln($tab->html($html->get_indent()));
          }
          $html->appendln('</div>');
          $html->appendln('</div>');
          ;
         */
        $html = new CStringBuilder();
        $html->setIndent($indent);
        $ajax_class = '';
        if ($this->ajax) {
            $ajax_class = 'ajax';
        //we create the ajax url if there are no url on tab
        } else {
            foreach ($this->tabs as $tab) {
                $tab->set_ajax(false);
            }
        }

        $classes = $this->classes;
        $classes = implode(' ', $classes);
        if (strlen($classes) > 0) {
            $classes = ' ' . $classes;
        }
        $classes .= ' ' . $ajax_class;

        $widget_classes = $this->widget_class;
        $widget_classes = implode(' ', $widget_classes);
        if (strlen($widget_classes) > 0) {
            $widget_classes = ' ' . $widget_classes;
        }

        $html->appendln('<div class="row-fluid tab-list ' . $classes . '" id="' . $this->id . '">');
        $html->appendln('	<div class="span12">');

        $html->appendln('		<div class="row-fluid">');
        if ($this->tab_position == 'top') {
            $html->appendln('           <div class="row-tab-menu">');
        } else {
            $html->appendln('			<div class="span2">');
        }

        if ($this->tab_position == 'top') {
            $html->appendln('               <div class="top-nav-container">');
        } else {
            $html->appendln('				<div class="side-nav-container affix-top">');
        }

        $html->appendln('					<ul id="' . $this->id . '-tab-nav" class="nav nav-tabs nav-stacked">');

        $active_tab = null;
        foreach ($this->tabs as $tab) {
            if (strlen($this->active_tab) == 0) {
                $this->active_tab($tab->id);
            }
            if ($tab->id == $this->active_tab) {
                $tab->set_active(true);
                $active_tab = $tab;
            }
            $tab->set_target('' . $this->id . '-ajax-tab-content');

            $html->appendln($tab->header_html($html->getIndent()));
        }
        $active_tab_icon = '';
        $active_tab_label = '';

        if ($active_tab != null) {
            $active_tab_icon = $active_tab->getIcon();
            $active_tab_label = $active_tab->getLabel();

            if (strlen($active_tab->getAjaxUrl()) > 0) {
                $tab_ajax_url = $active_tab->getAjaxUrl();

                /*
                  $url_base = curl::base();
                  if(substr($tab_ajax_url,0,strlen($url_base)) == $url_base) {
                  $tab_ajax_url = substr($tab_ajax_url,strlen($url_base));
                  }
                  $tab_ajax_url = curl::base(true,'http').$tab_ajax_url;
                 */
                //$active_tab_content = CCurl::factory($tab_ajax_url)->exec()->response();
            }
        }
        $html->appendln('					</ul>');
        $html->appendln('				</div>');
        $html->appendln('			</div>');

        if ($this->tab_position == 'top') {
            $html->appendln('           <div class="row-tab-content">');
        } else {
            $html->appendln('           <div class="span10">');
        }

        $html->appendln('				<div id="' . $this->id . '-tab-widget" class="widget-box nomargin widget-transaction-tab ' . $widget_classes . '">');
        if ($this->tab_position != 'top') {
            $html->appendln('					<div class="widget-title">');
        }

        if ($this->tab_position != 'top') {
            if ($this->have_icon) {
                $html->appendln('						<span class="icon">');
                $html->appendln('							<i class="icon-' . $active_tab_icon . '"></i>');
                $html->appendln('						</span>');
            }
            //$html->appendln('						<h5>' . $active_tab_label . '</h5>');
            $html->appendln('					</div>');
        }

        $html->appendln('					<div class="widget-content">');

        if ($this->ajax) {
            $html->appendln('						<div id="' . $this->id . '-ajax-tab-content">');
            $html->appendln('						</div>');
        } else {
            foreach ($this->tabs as $tab) {
                $html->appendln($tab->html($html->getIndent()));
            }
        }

        $html->appendln('					</div>');
        $html->appendln('				</div>');
        $html->appendln('			</div>');
        $html->appendln('		</div>');
        $html->appendln('	</div>');
        $html->appendln('</div>');

        if ($this->tab_position == 'top') {
            $html->appendln('
                    <style>
                        .top-nav-container ul {
                            margin-bottom: 0px;
                        }

                        .top-nav-container .nav-stacked > li {
                            display: inline-block
                        }

                        .top-nav-container .nav-stacked li  a {
                            border-radius: 0px;
                            background-color: #fff;
                            color: #333;

                            border-bottom: 1px solid #d4d4d6;
                        }

                        .top-nav-container .nav-stacked li.active a {
                            color: #2095f2;
                            background-color: #fff;
                            border-top: 1px solid #d4d4d6;
                            border-left: 1px solid #d4d4d6;
                            border-right: 1px solid #d4d4d6;
                            border-bottom: 1px solid #fff;
                            font-weight: bold;
                        }

                        .row-tab-content .widget-box .widget-content {
                            background-color: #fff;
                        }

                        .row-tab-menu {
                            z-index: 2;
                            position: relative;
                        }
                        .row-tab-content {
                            z-index: 1;
                            position: relative;');

            $html->appendln('margin-top: 0px;');

            $html->appendln('
                        }
                    </style>');
        }

        return $html->text();
    }

    public function js($indent = 0) {
        $js = new CStringBuilder();
        $js->setIndent($indent);
        foreach ($this->tabs as $tab) {
            $js->appendln($tab->js($js->getIndent()));
        }
        $js->appendln("

			jQuery('#" . $this->id . ' #' . $this->id . "-tab-nav > li > a.tab-ajax-load').click(function(event) {
				event.preventDefault();
				var target = jQuery(this).attr('data-target');
				var url = jQuery(this).attr('data-url');
				var method = jQuery(this).attr('data-method');
				if(!method) method='get';
				var pare = jQuery(this).parent();

				if(pare.prop('tagName')=='LI') {

					pare.parent().children().removeClass('active');
					pare.addClass('active');
                                        pare.parent().find('> li > a').removeClass('active');
					pare.find('> a').addClass('active');
				}
				jQuery(this).parent().children().removeClass('active');
				jQuery(this).addClass('active');

                                jQuery(this).parent().find('> li > a').removeClass('active');
                                jQuery(this).find('> a').addClass('active');
				var widget_tab = jQuery('#" . $this->id . "-tab-widget');
				if(widget_tab.length>0) {



					var data_icon = jQuery(this).attr('data-icon');
					var data_class = jQuery(this).attr('data-class');
					var data_text = jQuery(this).text();
					if(data_icon) widget_tab.find('.widget-title .icon i').first().attr('class',data_icon);

					if(data_text) widget_tab.find('.widget-title h5').first().html(data_text);
					var widget_content = widget_tab.find('.widget-content').first();
					widget_content.removeAttr('class').addClass('widget-content');

					if(data_class) widget_content.addClass(data_class);
				}

				if(jQuery('#" . $this->id . "').hasClass('ajax')) {
					$.cresenity.reload(target,url,method);
				}
                                else {
					var tab_id = jQuery(this).attr('data-tab');
					jQuery('#'+tab_id).parent().children().hide();
					jQuery('#'+tab_id).show();
				}

			});

		");

        $js->appendln("
                        //console.log(jQuery('#" . $this->id . "').find('li.active a.tab-ajax-load').attr('data-tab'));
			jQuery('#" . $this->id . "').find('li.active a.tab-ajax-load').click();
                        if(!jQuery('#" . $this->id . "').hasClass('ajax')) {
                            setTimeout(function() {
                                //console.log('BB');
                                //console.log(jQuery('#" . $this->id . "').find('li.active a.tab-ajax-load').attr('data-tab'));

                                jQuery('#" . $this->id . "').find('li.active a.tab-ajax-load').click();
                            },500);
                        }

		");

        return $js->text();
    }
}
//@codingStandardsIgnoreEnd
