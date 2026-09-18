<?php

/**
 * Description of HandleExceptions.
 */
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\ErrorHandler\Error\FatalError;

class CBootstrap_HandleExceptionBootstrapper extends CBootstrap_BootstrapperAbstract {
    /**
     * Reserved memory so that errors can be displayed properly on memory exhaustion.
     *
     * @var string
     */
    public static $reservedMemory;

    /**
     * Bootstrap the given application.
     *
     * @return void
     */
    public function bootstrap() {
        self::$reservedMemory = str_repeat('x', 10240);

        //error_reporting(-1);

        set_error_handler([$this, 'handleError']);

        set_exception_handler([$this, 'handleException']);

        register_shutdown_function([$this, 'handleShutdown']);

        if (CF::isProduction()) {
            ini_set('display_errors', 'Off');
        }
    }

    /**
     * Convert PHP errors to ErrorException instances.
     *
     * @param int    $level
     * @param string $message
     * @param string $file
     * @param int    $line
     * @param array  $context
     *
     * @throws \ErrorException
     *
     * @return void
     */
    public function handleError($level, $message, $file = '', $line = 0, $context = []) {
        if (error_reporting() & $level) {
            throw new ErrorException($message, 0, $level, $file, $line);
        }
    }

    /**
     * Handle an uncaught exception from the application.
     *
     * Note: Most exceptions can be handled via the try / catch block in
     * the HTTP and Console kernels. But, fatal error exceptions must
     * be handled differently since they are not normal exceptions.
     *
     * @param \Throwable $e
     *
     * @return void
     */
    public function handleException($e) {
        try {
            self::$reservedMemory = null;

            $this->getExceptionHandler()->report($e);
        } catch (Exception $e) {
        }

        if (CF::isCli()) {
            $this->renderForConsole($e);
        } else {
            $this->renderHttpResponse($e);
        }
    }

    /**
     * Render an exception to the console.
     *
     * @param \Throwable $e
     *
     * @return void
     */
    protected function renderForConsole($e) {
        $this->getExceptionHandler()->renderForConsole(new ConsoleOutput(), $e);
    }

    /**
     * Render an exception as an HTTP response and send it.
     *
     * @param \Throwable $e
     *
     * @return void
     */
    protected function renderHttpResponse($e) {
        $this->getExceptionHandler()->render(CHTTP::request(), $e)->send();
    }

    /**
     * Handle the PHP shutdown event.
     *
     * @return void
     */
    public function handleShutdown() {
        if (!is_null($error = error_get_last()) && $this->isFatal($error['type'])) {
            $this->handleException($this->fatalErrorFromPhpError($error, 0));
        }
    }

    /**
     * Create a new fatal error instance from an error array.
     *
     * @param array    $error
     * @param null|int $traceOffset
     *
     * @return \Symfony\Component\ErrorHandler\Error\FatalError
     */
    protected function fatalErrorFromPhpError(array $error, $traceOffset = null) {
        return new FatalError($error['message'], 0, $error, $traceOffset);
    }

    /**
     * Determine if the error type is fatal.
     *
     * @param int $type
     *
     * @return bool
     */
    protected function isFatal($type) {
        return in_array($type, [E_COMPILE_ERROR, E_CORE_ERROR, E_ERROR, E_PARSE]);
    }

    /**
     * Get an instance of the exception handler.
     *
     * @return CException_ExceptionHandler
     */
    protected function getExceptionHandler() {
        return CException::exceptionHandler();
    }
}
