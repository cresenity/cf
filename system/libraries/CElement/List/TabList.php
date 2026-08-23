<?php

defined('SYSPATH') or die('No direct access allowed.');

class CElement_List_TabList extends CElement_List {
    use CTrait_Compat_Element_TabList;

    /**
     * Tabs.
     *
     * @var CElement_List_TabList_Tab[]
     */
    protected $tabs;

    /**
     * 'left' (vertical, nav-stacked) or 'top'.
     *
     * @var string
     */
    protected $tabPosition;

    /**
     * Id of the currently active tab.
     *
     * @var string
     */
    protected $activeTab;

    /**
     * Whether tab content is ajax-loaded into the shared content pane rather
     * than all tabs being rendered inline and toggled with show()/hide().
     *
     * @var bool
     */
    protected $ajax;

    /**
     * Whether the active tab's icon is shown in the widget header. Always
     * false -- no public setter exists.
     *
     * @var bool
     */
    protected $haveIcon;

    /**
     * Extra CSS classes for the tab content widget wrapper, see addWidgetClass().
     *
     * @var string[]
     */
    protected $widgetClass;

    /**
     * Optional extra element rendered alongside the tab nav, see header().
     *
     * @var null|CElement_Element_Div
     */
    protected $header;

    /**
     * Extra data merged into each ajax tab's reload request.
     *
     * @var array
     */
    protected $paramRequest;

    /**
     * CSS class for the tab content widget's outer wrapper, from the 'widget.class.wrapper' theme setting.
     *
     * @var string
     */
    protected $widgetWrapperClass;

    /**
     * CSS class for the tab content widget's body, from the 'widget.class.body' theme setting.
     *
     * @var string
     */
    protected $widgetBodyClass;

    /**
     * CSS class for the tab content widget's header, from the 'widget.class.header' theme setting.
     *
     * @var string
     */
    protected $widgetHeaderClass;

    /**
     * @param null|string $id
     *
     * @return void
     */
    public function __construct($id = null) {
        parent::__construct($id);

        $this->tabPosition = 'left';
        $this->activeTab = '';
        $this->ajax = true;
        $this->haveIcon = false;
        $this->tabs = [];
        $this->widgetClass = [];
        $this->header = null;
        $this->paramRequest = [];
        $this->widgetWrapperClass = c::theme('widget.class.wrapper', 'widget-box');
        $this->widgetBodyClass = c::theme('widget.class.body', 'widget-content');
        $this->widgetHeaderClass = c::theme('widget.class.header', 'widget-title');
    }

    /**
     * @param null|string $id
     *
     * @return \CElement_List_TabList
     */
    public static function factory($id = null) {
        /** @phpstan-ignore-next-line */
        return new static($id);
    }

    /**
     * @return CElement_Element_Div
     */
    public function header() {
        if ($this->header == null) {
            $this->header = CElement_Factory::createElement('div')->addClass('ml-auto');
        }

        return $this->header;
    }

    /**
     * @param string $id
     *
     * @return CElement_List_TabList_Tab
     */
    public function addTab($id = '') {
        $tab = CElement_List_TabList_Tab::factory($id)->setTabList($this);
        if (strlen($this->activeTab) == 0) {
            $this->activeTab = $tab->id();
        }
        $this->tabs[] = $tab;

        return $tab;
    }

    /**
     * @param string $tabId
     *
     * @return CElement_List_TabList
     */
    public function setActiveTab($tabId) {
        $this->activeTab = $tabId;

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return CElement_List_TabList
     */
    public function setAjax($bool = true) {
        $this->ajax = $bool;

        return $this;
    }

    /**
     * @param string $tabPosition
     *
     * @return CElement_List_TabList
     */
    public function setTabPosition($tabPosition) {
        $this->tabPosition = $tabPosition;

        return $this;
    }

    /**
     * @return CElement_List_TabList
     */
    public function setTabPositionLeft() {
        return $this->setTabPosition('left');
    }

    /**
     * @return CElement_List_TabList
     */
    public function setTabPositionTop() {
        return $this->setTabPosition('top');
    }

    /**
     * @return CElement_List_TabList
     */
    public function setParamRequest(array $paramRequest) {
        $this->paramRequest = $paramRequest;

        return $this;
    }

    /**
     * @param string|array $class
     *
     * @return CElement_List_TabList
     */
    public function addWidgetClass($class) {
        if (is_array($class)) {
            $this->widgetClass = array_merge($this->widgetClass, $class);
        } else {
            $this->widgetClass[] = $class;
        }

        return $this;
    }

    /**
     * Id of the shared container every ajax tab renders into.
     *
     * Exposed because code rendering a tab's own content runs in a separate
     * request and has no TabList instance to ask - without this it would have
     * to repeat the suffix by hand to target the container.
     *
     * @param string $tabListId
     *
     * @return string
     */
    public static function ajaxContentId($tabListId) {
        return $tabListId . '-ajax-tab-content';
    }

    /**
     * Rendering/behavior is handled client-side by cres.js (see
     * media/js/cres/src/element/component/TabList), the same way
     * CElement_Component_Widget/TreeView/Calendar work: this only builds the
     * markup (via addView()) and passes config through the `cres-config` attribute.
     *
     * @return void
     */
    public function build() {
        if (!$this->ajax) {
            // No fallback ajax url gets generated for any tab -- see Tab::resolveAjaxUrl().
            foreach ($this->tabs as $tab) {
                $tab->setAjax(false);
            }
        }

        if (strlen($this->activeTab) == 0 && count($this->tabs) > 0) {
            $this->setActiveTab($this->tabs[0]->id());
        }

        $activeTab = null;
        foreach ($this->tabs as $tab) {
            if ($tab->id() == $this->activeTab) {
                $tab->setActive(true);
                $activeTab = $tab;
            }
            $tab->setTarget(static::ajaxContentId($this->id));
            $tab->resolveAjaxUrl();
        }

        if ($this->header != null) {
            // $header isn't a real child (see header()), so its own js() would
            // never be collected by the default js() traversal otherwise.
            $this->addJs($this->header->js());
        }

        $cresConfig = [
            'ajax' => $this->ajax,
            'paramRequest' => $this->paramRequest,
            'widgetBodyClass' => $this->widgetBodyClass,
        ];

        $this->addView('cresenity.element.list.tab-list.index', [
            'id' => $this->id,
            'ajaxContentId' => static::ajaxContentId($this->id),
            'classes' => trim(implode(' ', $this->classes) . ($this->tabPosition == 'left' ? ' vtabs' : '')),
            'cresConfig' => $cresConfig,
            'tabs' => $this->tabs,
            'activeTab' => $activeTab,
            'tabPosition' => $this->tabPosition,
            'ajax' => $this->ajax,
            'header' => $this->header,
            'haveIcon' => $this->haveIcon,
            'widgetWrapperClass' => $this->widgetWrapperClass,
            'widgetBodyClass' => $this->widgetBodyClass,
            'widgetHeaderClass' => $this->widgetHeaderClass,
            'widgetClasses' => implode(' ', $this->widgetClass),
        ]);
    }
}
