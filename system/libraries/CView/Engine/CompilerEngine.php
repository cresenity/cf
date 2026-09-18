<?php

/**
 * Description of CompilerEngine.
 */
class CView_Engine_CompilerEngine extends CView_Engine_PhpEngine {
    use CView_Concern_BladeCollectViewExceptionTrait;

    /**
     * The Blade compiler instance.
     *
     * @var CView_CompilerInterface
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
     * @return void
     */
    protected $currentPath = null;

    public function __construct() {
        $this->compiler = CView_Compiler_BladeCompiler::instance();
        $this->compiler->component('dynamic-component', \CView_Component_DynamicComponent::class);
    }

    /**
     * Get the evaluated contents of the view.
     *
     * @param string $path
     * @param array  $data
     *
     * @return string
     */
    public function get($path, array $data = []) {
        $this->currentPath = $path;

        $this->collectViewData($path, $data);
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
     * @param \Throwable $baseException
     * @param int        $obLevel
     *
     * @throws \Throwable
     *
     * @return void
     */
    protected function handleViewException($baseException, $obLevel) {
        while (ob_get_level() > $obLevel) {
            ob_end_clean();
        }

        if ($baseException instanceof CView_Exception_ViewException) {
            throw $baseException;
        }

        $viewExceptionClass = CView_Exception_ViewException::class;

        if ($baseException instanceof CException_Contract_ProvideSolutionInterface) {
            $viewExceptionClass = CView_Exception_ViewWithSolutionException::class;
        }

        $exception = new $viewExceptionClass(
            $this->getMessage($baseException),
            0,
            1,
            $this->getCompiledViewName($baseException->getFile()),
            $this->getBladeLineNumber($baseException->getFile(), $baseException->getLine()),
            $baseException
        );

        if ($baseException instanceof CException_Contract_ProvideSolutionInterface) {
            $exception->setSolution($baseException->getSolution());
        }

        $this->modifyViewsInTrace($exception);

        $exception->setView($this->getCompiledViewName($baseException->getFile()));
        $exception->setViewData($this->getCompiledViewData($baseException->getFile()));

        throw $exception;
    }

    /**
     * Get the exception message for an exception.
     *
     * @param \Throwable $e
     *
     * @return string
     */
    protected function getMessage($e) {
        return $e->getMessage() . ' (View: ' . realpath(carr::last($this->lastCompiled)) . ', Compiled:' . $e->getFile() . ')';
    }

    /**
     * Get the compiler implementation.
     *
     * @return \CView_CompilerInterface
     */
    public function getCompiler() {
        return $this->compiler;
    }

    protected function getBladeLineNumber($compiledPath, $exceptionLineNumber) {
        $viewPath = $this->getCompiledViewName($compiledPath);
        if (!$viewPath) {
            return $exceptionLineNumber;
        }

        $sourceMapCompiler = new CView_Compiler_BladeSourceMapCompiler();

        return $sourceMapCompiler->detectLineNumber($viewPath, $exceptionLineNumber);
    }

    protected function modifyViewsInTrace(CView_Exception_ViewException $exception) {
        $trace = CCollection::make($exception->getPrevious()->getTrace())
            ->map(function ($trace) {
                if ($compiledData = $this->findCompiledView(carr::get($trace, 'file', ''))) {
                    $trace['file'] = $compiledData['path'];
                    $trace['line'] = $this->getBladeLineNumber($trace['file'], $trace['line']);
                }

                return $trace;
            })->toArray();

        $traceProperty = new ReflectionProperty('Exception', 'trace');
        $traceProperty->setAccessible(true);
        $traceProperty->setValue($exception, $trace);
    }
}
