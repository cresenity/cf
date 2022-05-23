<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Sep 2, 2018, 11:02:36 PM
 */
class CJavascript_StatementFactory {
    public static function createJQuery($selector = 'this') {
        return new CJavascript_Statement_JQuery($selector);
    }

    public static function createVariable($varName, $varValue = null) {
        return new CJavascript_Statement_Variable($varName, $varValue);
    }

    /**
     * @param string $js
     *
     * @return CJavascript_Statement_Raw
     */
    public static function createRaw($js) {
        return new CJavascript_Statement_Raw($js);
    }

    /**
     * @param string $functionName
     * @param array  $functionParameter
     *
     * @return CJavascript_Statement_Function
     */
    public static function createFunction($functionName, $functionParameter = []) {
        return new CJavascript_Statement_Function($functionName, $functionParameter);
    }

    /**
     * @param mixed $operand1
     * @param mixed $operator
     * @param mixed $operand2
     *
     * @return CJavascript_Statement_IfStatement
     */
    public static function createIf($operand1, $operator, $operand2) {
        return new CJavascript_Statement_IfStatement($operand1, $operator, $operand2);
    }
}
