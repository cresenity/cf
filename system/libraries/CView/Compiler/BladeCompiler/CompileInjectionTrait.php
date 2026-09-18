<?php

/**
 * Description of CompileInjection
 */

trait CView_Compiler_BladeCompiler_CompileInjectionTrait {
    /**
     * Compile the inject statements into valid PHP.
     *
     * @param string $expression
     *
     * @return string
     */
    protected function compileInject($expression) {
        $segments = explode(',', preg_replace("/[\(\)\\\"\']/", '', $expression));

        $variable = trim($segments[0]);

        $service = trim($segments[1]);

        return "<?php \${$variable} = CContainer::getInstance()->make('{$service}'); ?>";
    }
}
