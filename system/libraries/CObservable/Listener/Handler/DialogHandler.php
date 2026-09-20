<?php

defined('SYSPATH') or die('No direct access allowed.');

class CObservable_Listener_Handler_DialogHandler extends CObservable_Listener_Handler {
    use CTrait_Compat_Handler_Driver_Dialog,
        CObservable_Listener_Handler_Trait_AjaxHandlerTrait,
        CObservable_Listener_Handler_Trait_TargetHandlerTrait,
        CObservable_Listener_Handler_Trait_CloseHandlerTrait,
        CTrait_Element_Property_Title;

    /**
     * @var string
     */
    protected $content;

    /**
     * @var array
     */
    protected $param;

    /**
     * @var array
     */
    protected $actions;

    /**
     * @var array
     */
    protected $param_inputs;

    /**
     * @var array
     */
    protected $param_request;

    /**
     * @var bool
     */
    protected $isSidebar;

    /**
     * @var bool
     */
    protected $isFull;

    /**
     * @var string
     */
    protected $modalClass;

    /**
     * @var mixed
     */
    protected $backdrop;

    /**
     * @param CObservable_Listener $listener
     */
    public function __construct($listener) {
        parent::__construct($listener);
        $this->name = 'Dialog';
        $this->method = 'get';
        $this->target = '';
        $this->content = CObservable_HandlerElement::factory();
        $this->actions = CElement_List_ActionList::factory();
        $this->param_inputs = [];
        $this->param_request = [];
        $this->title = 'Detail';
        $this->js_class = null;
        $this->js_class_manual = null;
    }

    public function setSidebar($bool = true) {
        $this->isSidebar = $bool;

        return $this;
    }

    public function setFull($bool = true) {
        $this->isFull = $bool;

        return $this;
    }

    /**
     * @param string $class
     *
     * @return $this
     */
    public function setModalClass($class) {
        $this->modalClass = $class;

        return $this;
    }

    /**
     * @param mixed $backdrop
     *
     * @return $this
     */
    public function setBackdrop($backdrop) {
        $this->backdrop = $backdrop;

        return $this;
    }

    public function addParamInput($inputs) {
        if (!is_array($inputs)) {
            $inputs = [$inputs];
        }
        foreach ($inputs as $inp) {
            $this->param_inputs[] = $inp;
        }

        return $this;
    }

    public function addParamRequest($param_request) {
        if (!is_array($param_request)) {
            $param_request = [$param_request];
        }
        foreach ($param_request as $req_k => $req_v) {
            $this->param_request[$req_k] = $req_v;
        }

        return $this;
    }

    /**
     * @param string $method
     *
     * @return $this
     */
    public function setMethod($method) {
        $this->method = $method;

        return $this;
    }

    public function content() {
        return $this->content;
    }

    public function js() {
        $js = '';
        if (strlen($this->target) == 0) {
            $this->target = 'modal_opt_' . $this->event . '_' . $this->owner . '_dialog';
        }

        $dataAddition = '';

        foreach ($this->param_inputs as $inp) {
            if (strlen($dataAddition) > 0) {
                $dataAddition .= ',';
            }
            $dataAddition .= "'" . $inp . "':cresenity.value('#" . $inp . "')";
        }
        foreach ($this->param_request as $req_k => $req_v) {
            if (strlen($dataAddition) > 0) {
                $dataAddition .= ',';
            }
            $dataAddition .= "'" . $req_k . "':'" . $req_v . "'";
        }
        $dataAddition = '{' . $dataAddition . '}';

        $generatedUrl = $this->generatedUrl();

        $reloadOptions = '{';
        $reloadOptions .= "url:'" . $generatedUrl . "',";
        $reloadOptions .= "method:'" . $this->method . "',";
        $reloadOptions .= 'dataAddition:' . $dataAddition . ',';
        $reloadOptions .= '}';

        $backdropValue = "'static'";

        if (is_bool($this->backdrop)) {
            $backdropValue = $this->backdrop ? 'true' : 'false';
        }

        $jsOptions = '{';
        $jsOptions .= "selector:'#" . $this->target . "',";
        $jsOptions .= "title:'" . $this->title . "',";
        $jsOptions .= "modalClass:'" . $this->modalClass . "',";
        $jsOptions .= 'backdrop:' . $backdropValue . ',';
        $jsOptions .= 'reload:' . $reloadOptions . ',';
        if ($this->haveCloseListener()) {
            $jsOptions .= 'onClose:' . $this->getCloseListener()->js() . ',';
        }
        $jsOptions .= 'isSidebar:' . ($this->isSidebar ? 'true' : 'false') . ',';
        $jsOptions .= 'isFull:' . ($this->isFull ? 'true' : 'false') . ',';

        $jsOptions .= '}';

        $js_class = CApp_Config::get('js_class');
        if (strlen($js_class) > 0) {
            $this->js_class = $js_class;
        }
        if ($this->js_class_manual != null) {
            $this->js_class = $this->js_class_manual;
        }
        if (strlen($this->js_class) > 0 && $this->js_class != 'cresenity') {
            if ($this->content instanceof CObservable_HandlerElement) {
                $content = $this->content->html();
            } else {
                $content = $this->content;
            }
            $content = addslashes($content);
            $content = str_replace("\r\n", '', $content);
            if (strlen(trim($content)) == 0) {
                $content = $this->generatedUrl();
            }
            $js .= '
                $.' . $this->js_class . ".show_dialog('" . $this->target . "','" . $this->title . "','" . $content . "');
            ";
        } else {
            $js .= 'cresenity.modal(' . $jsOptions . ');';
        }

        return $js;
    }
}
