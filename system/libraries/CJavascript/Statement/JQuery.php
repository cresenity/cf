<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Sep 1, 2018, 11:48:25 PM
 *
 * @method CJavascript_Statement_JQuery toggle()
 */
class CJavascript_Statement_JQuery extends CJavascript_Statement {
    /**
     * @var string
     */
    protected $selector;

    /**
     * @var CJavascript_Statement_JQuery_CompilableInterface[]
     */
    protected $needForCompile;

    public function __construct($selector = 'this') {
        $this->selector = $selector;
        $this->methods = [];
    }

    public function getSelector() {
        return $this->selector;
    }

    public function setSelector($selector = 'this') {
        $this->selector = $selector;

        return $this;
    }

    public function ajax($options = []) {
        $ajaxObject = new CJavascript_Statement_JQuery_Ajax($options);
        $this->needForCompile[] = $ajaxObject;

        return $this;
    }

    public function event($eventName, $js, $options = []) {
        $eventObject = new CJavascript_Statement_JQuery_Event($eventName, $js, $options);

        $this->needForCompile[] = $eventObject;

        return $this;
    }

    public function __call($method, $arguments) {
        $methodObject = new CJavascript_Statement_JQuery_Method($method, $arguments);
        $this->needForCompile[] = $methodObject;

        return $this;
    }

    protected function compile() {
        $haveAjax = false;
        $element = '"' . addslashes($this->selector) . '"';
        $jQueryObjectStr = "$({$element})";
        $str = '';
        foreach ($this->needForCompile as $compilable) {
            if ($compilable instanceof CJavascript_Statement_JQuery_Ajax) {
                $haveAjax = true;
            }
            $str .= $compilable->compile();
        }
        if ($haveAjax) {
            $jQueryObjectStr = '$';
        }
        $str = $jQueryObjectStr . $str . ';';

        return $str;
    }

    public function getStatement() {
        $statement = $this->compile();

        return $statement;
    }
}
