<?php

/**
 * Description of collector.
 *
 * @author Hery
 */
return [
    'exception' => false,
    //JS/browser error collector (cresjs collector, system/libraries/CDebug/Collector/
    //JsException.php) - separate flag on purpose, not reused from 'exception' above: an app
    //opting into PHP exception collection should not silently also start sending browser error
    //traffic to devcloud until that is reviewed for that app specifically.
    'js_exception' => false,
];
