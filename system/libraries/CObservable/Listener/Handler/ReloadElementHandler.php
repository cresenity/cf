<?php

/**
 * Description of ReloadElementHandler
 */
class CObservable_Listener_Handler_ReloadElementHandler extends CObservable_Listener_Handler {
    use CObservable_Listener_Handler_Trait_TargetHandlerTrait,
        CObservable_Listener_Handler_Trait_SelectorHandlerTrait;

    public function __construct($listener) {
        parent::__construct($listener);

        $this->target = '';
        $this->selector = '';
    }

    public function js() {
        $jsOptions = '{';
        $jsOptions .= "selector:'" . $this->getSelector() . "',";
        $jsOptions .= '}';

        $js = '';
        $js .= '
                cresenity.reload(' . $jsOptions . ');
         ';

        return $js;
    }
}
