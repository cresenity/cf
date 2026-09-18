<?php

/**
 * Description of CompileHelperTrait.
 */
trait CView_Compiler_BladeCompiler_CompileHelperTrait {
    /**
     * Compile the CSRF statements into valid PHP.
     *
     * @return string
     */
    protected function compileCsrf() {
        return '<?php echo c::csrfField(); ?>';
    }

    /**
     * Compile the "dd" statements into valid PHP.
     *
     * @param string $arguments
     *
     * @return string
     */
    protected function compileDd($arguments) {
        return "<?php cdbg::dd{$arguments}; ?>";
    }

    /**
     * Compile the "dump" statements into valid PHP.
     *
     * @param string $arguments
     *
     * @return string
     */
    protected function compileDump($arguments) {
        return "<?php cdbg::d{$arguments}; ?>";
    }

    /**
     * Compile the method statements into valid PHP.
     *
     * @param string $method
     *
     * @return string
     */
    protected function compileMethod($method) {
        return "<?php echo c::methodField{$method}; ?>";
    }

    /**
     * Compile the "vite" statements into valid PHP.
     *
     * @param null|string $arguments
     *
     * @return string
     */
    protected function compileVite($arguments) {
        $arguments ??= '()';

        $class = CBase_Vite::class;

        return "<?php echo c::container('$class'){$arguments}; ?>";
    }

    /**
     * Compile the "viteReactRefresh" statements into valid PHP.
     *
     * @return string
     */
    protected function compileViteReactRefresh() {
        $class = CBase_Vite::class;

        return "<?php echo c::container('$class')->reactRefresh(); ?>";
    }
}
