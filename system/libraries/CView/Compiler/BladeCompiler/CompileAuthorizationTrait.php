<?php

/**
 * Description of CompilesAuthorizationTrait.
 */
trait CView_Compiler_BladeCompiler_CompileAuthorizationTrait {
    /**
     * Compile the can statements into valid PHP.
     *
     * @param string $expression
     *
     * @return string
     */
    protected function compileCan($expression) {
        return "<?php if (c::gate()->check{$expression}): ?>";
    }

    /**
     * Compile the cannot statements into valid PHP.
     *
     * @param string $expression
     *
     * @return string
     */
    protected function compileCannot($expression) {
        return "<?php if (c::gate()->denies{$expression}): ?>";
    }

    /**
     * Compile the canany statements into valid PHP.
     *
     * @param string $expression
     *
     * @return string
     */
    protected function compileCanany($expression) {
        return "<?php if (c::gate()->any{$expression}): ?>";
    }

    /**
     * Compile the else-can statements into valid PHP.
     *
     * @param string $expression
     *
     * @return string
     */
    protected function compileElsecan($expression) {
        return "<?php elseif (c::gate()->check{$expression}): ?>";
    }

    /**
     * Compile the else-cannot statements into valid PHP.
     *
     * @param string $expression
     *
     * @return string
     */
    protected function compileElsecannot($expression) {
        return "<?php elseif (c::gate()->denies{$expression}): ?>";
    }

    /**
     * Compile the else-canany statements into valid PHP.
     *
     * @param string $expression
     *
     * @return string
     */
    protected function compileElsecanany($expression) {
        return "<?php elseif (c::gate()->any{$expression}): ?>";
    }

    /**
     * Compile the end-can statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndcan() {
        return '<?php endif; ?>';
    }

    /**
     * Compile the end-cannot statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndcannot() {
        return '<?php endif; ?>';
    }

    /**
     * Compile the end-canany statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndcanany() {
        return '<?php endif; ?>';
    }
}
