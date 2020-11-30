<?php

/**
 * Description of CompilerEngine
 *
 * @author Hery
 */
class CView_Engine_CompilerEngine extends CView_Engine_PhpEngine {

    /**
     * The Blade compiler instance.
     *
     * @var CView_CompilerAbstract
     */
    protected $compiler;

    /**
     * A stack of the last compiled templates.
     *
     * @var array
     */
    protected $lastCompiled = [];

    /**
     * Create a new compiler engine instance.
     *
     * @param  CView_CompilerAbstract  $compiler
     * @return void
     */
    public function __construct() {
        $this->compiler = CView_Compiler_BladeCompiler::instance();
        $this->compiler->component('dynamic-component', CView_Component_DynamicComponent::class);
    }

    /**
     * Get the evaluated contents of the view.
     *
     * @param  string  $path
     * @param  array  $data
     * @return string
     */
    public function get($path, array $data = []) {
        $this->lastCompiled[] = $path;

        // If this given view has expired, which means it has simply been edited since
        // it was last compiled, we will re-compile the views so we can evaluate a
        // fresh copy of the view. We'll pass the compiler the path of the view.
        if ($this->compiler->isExpired($path)) {
            $this->compiler->compile($path);
        }

        // Once we have the path to the compiled file, we will evaluate the paths with
        // typical PHP just like any other templates. We also keep a stack of views
        // which have been rendered for right exception messages to be generated.
        $results = $this->evaluatePath($this->compiler->getCompiledPath($path), $data);

        array_pop($this->lastCompiled);

        return $results;
    }

    /**
     * Handle a view exception.
     *
     * @param  \Throwable  $e
     * @param  int  $obLevel
     * @return void
     *
     * @throws \Throwable
     */
    protected function handleViewException($e, $obLevel) {
        $e = new ErrorException($this->getMessage($e), 0, 1, $e->getFile(), $e->getLine(), $e);

        parent::handleViewException($e, $obLevel);
    }

    /**
     * Get the exception message for an exception.
     *
     * @param  \Throwable  $e
     * @return string
     */
    protected function getMessage($e) {
        return $e->getMessage() . ' (View: ' . realpath(carr::last($this->lastCompiled)) . ')';
    }

    /**
     * Get the compiler implementation.
     *
     * @return \Illuminate\View\Compilers\CompilerInterface
     */
    public function getCompiler() {
        return $this->compiler;
    }

}
