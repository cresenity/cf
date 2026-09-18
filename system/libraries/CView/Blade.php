<?php

/**
 * Description of Blade
 */
class CView_Blade {
    public static function __callStatic($name, $arguments) {
        return call_user_func_array([CView_Compiler_BladeCompiler::instance(), $name], $arguments);
    }
}
