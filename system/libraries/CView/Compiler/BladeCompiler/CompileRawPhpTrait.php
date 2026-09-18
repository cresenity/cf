<?php

/**
 * Description of CompileRawPhp
 */
trait CView_Compiler_BladeCompiler_CompileRawPhpTrait {
    /**
     * Compile the raw PHP statements into valid PHP.
     *
     * @param string $expression
     *
     * @return string
     */
    protected function compilePhp($expression) {
        if ($expression) {
            return "<?php {$expression}; ?>";
        }

        return '@php';
    }

    /**
     * Compile the unset statements into valid PHP.
     *
     * @param string $expression
     *
     * @return string
     */
    protected function compileUnset($expression) {
        return "<?php unset{$expression}; ?>";
    }
}
