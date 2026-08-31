<?php

defined('SYSPATH') or die('No direct access allowed.');

use Psr\Log\LogLevel;
use Whoops\Run as Whoops;
use Whoops\Handler\HandlerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use League\Flysystem\PathTraversalDetected;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * @author Hery Kurniawans
 *
 * @see CException
 */
class CException_ExceptionHandler implements CException_ExceptionHandlerInterface {
    use CTrait_ReflectsClosureTrait;

    /**
     * The container implementation.
     *
     * @var CContainer_Container
     */
    protected $container;

    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [];

    /**
     * A list of the exception types that a queued job should never be retried for.
     *
     * @var array
     */
    protected $dontRetry = [];

    /**
     * The callbacks that decide whether a queued job should stop being retried.
     *
     * @var callable[]
     */
    protected $dontRetryCallbacks = [];

    /**
     * The callbacks that should be used during reporting.
     *
     * @var array
     */
    protected $reportCallbacks = [];

    /**
     * A map of exceptions with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [];

    /**
     * The callbacks that should be used during rendering.
     *
     * @var array
     */
    protected $renderCallbacks = [];

    /**
     * The registered exception mappings.
     *
     * @var array<string, \Closure>
     */
    protected $exceptionMap = [];

    /**
     * A list of the internal exception types that should not be reported.
     *
     * @var array
     */
    protected $internalDontReport = [
        CAuth_Exception_AuthenticationException::class,
        CAuth_Exception_AuthorizationException::class,
        HttpException::class,
        CHTTP_Exception_ResponseException::class,
        CModel_Exception_ModelNotFoundException::class,
        CDatabase_Exception_MultipleRecordsFoundException::class,
        CDatabase_Exception_RecordsNotFoundException::class,
        SuspiciousOperationException::class,
        CSession_Exception_TokenMismatchException::class,
        CValidation_Exception::class,
        CException_Contract_DontReportInterface::class
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        'passwordConfirmation',
        'confirm_password',
        'old_password',
        'new_password',
        'confirmPassword',
    ];

    /**
     * Create a new exception handler instance.
     *
     * s    * @return void
     */
    public function __construct() {
        $this->container = c::container();
    }

    /**
     * Register a reportable callback.
     *
     * @param callable $reportUsing
     *
     * @return CException_ReportableHandler
     */
    public function reportable(callable $reportUsing) {
        if (PHP_VERSION_ID >= 71000) {
            if (!$reportUsing instanceof Closure) {
                $reportUsing = Closure::fromCallable($reportUsing);
            }
        }

        return c::tap(new CException_ReportableHandler($reportUsing), function ($callback) {
            $this->reportCallbacks[] = $callback;
        });
    }

    /**
     * Register a renderable callback.
     *
     * @param callable $renderUsing
     *
     * @return $this
     */
    public function renderable(callable $renderUsing) {
        if (PHP_VERSION_ID >= 71000) {
            if (!$renderUsing instanceof Closure) {
                $renderUsing = Closure::fromCallable($renderUsing);
            }
        }
        $this->renderCallbacks[] = $renderUsing;

        return $this;
    }

    /**
     * Register a new exception mapping.
     *
     * @param \Closure|string      $from
     * @param null|\Closure|string $to
     *
     * @throws \InvalidArgumentException
     *
     * @return $this
     */
    public function map($from, $to = null) {
        if (is_string($to)) {
            $to = function ($exception) use ($to) {
                return new $to('', 0, $exception);
            };
        }

        if (is_callable($from) && is_null($to)) {
            $from = $this->firstClosureParameterType($to = $from);
        }

        if (!is_string($from) || !$to instanceof Closure) {
            throw new InvalidArgumentException('Invalid exception mapping.');
        }

        $this->exceptionMap[$from] = $to;

        return $this;
    }

    /**
     * Indicate that the given exception type should not be reported.
     *
     * @param string $class
     *
     * @return $this
     */
    public function ignore($class) {
        $this->dontReport[] = $class;

        return $this;
    }

    /**
     * Indicate that a queued job should not be retried when it fails with the
     * given exception type, or when the given callback answers true.
     *
     * @param callable|class-string<\Throwable> $type
     *
     * @return $this
     */
    public function dontRetry($type) {
        if (is_string($type)) {
            $this->dontRetry[] = $type;
        } else {
            $this->dontRetryCallbacks[] = $type;
        }

        return $this;
    }

    /**
     * Determine whether a queued job that failed with this exception should stop
     * being retried and be marked failed straight away.
     *
     * Read by CQueue_Worker::markJobAsFailedIfItShouldntBeRetried(). Retrying a
     * job that failed on a malformed payload or a deleted record only burns
     * attempts, so the answer belongs with the exception handler rather than
     * with every job.
     *
     * @param Throwable $e
     *
     * @return bool
     */
    public function shouldStopRetries($e) {
        $matched = carr::first($this->dontRetry, function ($type) use ($e) {
            return $e instanceof $type;
        });
        if (!is_null($matched)) {
            return true;
        }
        foreach ($this->dontRetryCallbacks as $dontRetryCallback) {
            if (call_user_func($dontRetryCallback, $e) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set the log level for the given exception type.
     *
     * @param class-string<\Throwable> $type
     * @param \Psr\Log\LogLevel::*     $level
     *
     * @return $this
     */
    public function level($type, $level) {
        $this->levels[$type] = $level;

        return $this;
    }

    /**
     * Report or log an exception.
     *
     * @param \Exception $e
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function report($e) {
        $e = $this->mapException($e);

        if ($this->shouldntReport($e)) {
            return;
        }
        if (CBase_Reflector::isCallable($reportCallable = [$e, 'report'])
            && $this->container->call($reportCallable) !== false
        ) {
            return;
        }

        foreach ($this->reportCallbacks as $reportCallback) {
            if ($reportCallback->handles($e)) {
                if ($reportCallback($e) === false) {
                    return;
                }
            }
        }

        $logger = CLogger::logger();
        // $level = carr::first(
        //     $this->levels,
        //     function ($level, $type) use ($e) {
        //         return $e instanceof $type;
        //     },
        //     LogLevel::ERROR
        // );
        $context = $this->buildExceptionContext($e);
        $logger->error(
            $e->getMessage(),
            $context,
        );
    }

    /**
     * Determine if the exception should be reported.
     *
     * @param \Exception $e
     *
     * @return bool
     */
    public function shouldReport($e) {
        return !$this->shouldntReport($e);
    }

    /**
     * Determine if the exception is in the "do not report" list.
     *
     * @param \Exception $e
     *
     * @return bool
     */
    protected function shouldntReport($e) {
        $dontReport = array_merge($this->dontReport, $this->internalDontReport);

        return !is_null(carr::first($dontReport, function ($type) use ($e) {
            return $e instanceof $type;
        }));
    }

    /**
     * Remove the given exception class from the list of exceptions that should be ignored.
     *
     * @param string $exception
     *
     * @return $this
     */
    public function stopIgnoring(string $exception) {
        $this->dontReport = c::collect($this->dontReport)
            ->reject(function ($ignored) use ($exception) {
                return $ignored === $exception;
            })->values()->all();

        $this->internalDontReport = c::collect($this->internalDontReport)
            ->reject(function ($ignored) use ($exception) {
                return $ignored === $exception;
            })->values()->all();

        return $this;
    }

    /**
     * Create the context array for logging the given exception.
     *
     * @param \Throwable $e
     *
     * @return array
     */
    protected function buildExceptionContext(Throwable $e) {
        return array_merge(
            $this->exceptionContext($e),
            $this->context(),
            ['exception' => $e]
        );
    }

    /**
     * Get the default exception context variables for logging.
     *
     * @param \Throwable $e
     *
     * @return array
     */
    protected function exceptionContext(Throwable $e) {
        if (method_exists($e, 'context')) {
            return $e->context();
        }

        return [];
    }

    /**
     * Get the default context variables for logging.
     *
     * @return array
     */
    protected function context() {
        try {
            return array_filter([
                'domain' => CF::domain(),
                'appCode' => CF::appCode(),
                'appId' => CF::appId(),
                'orgCode' => CF::orgCode(),
                'orgId' => CF::orgId(),
                'userId' => c::auth()->id(),
            ]);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Render an exception into a response.
     *
     * @param \CHTTP_Request $request
     * @param \Exception     $e
     *
     * @return \CHTTP_Response|\Symfony\Component\HttpFoundation\Response
     */
    public function render($request, $e) {
        if (method_exists($e, 'render')) {
            /** @var \Illuminate\Contracts\Support\Renderable $e */
            if ($response = $e->render($request)) {
                return c::router()->toResponse($request, $response);
            }
        }
        if ($e instanceof CInterface_Responsable) {
            return $e->toResponse($request);
        }

        $e = $this->prepareException($e);

        if ($request->isCresRequest()) {
            return $this->prepareJsonResponse($request, $e);
        }
        if ($e instanceof HttpExceptionInterface) {
            if ($e instanceof CHTTP_Exception_RedirectHttpException) {
                return c::redirect($e->getUri(), $e->getStatusCode());
            }

            if (CView::exists('errors/http/' . $e->getStatusCode())) {
                c::app()->registerCoreModules();

                return c::response()->view('errors/http/' . $e->getStatusCode(), [
                    'exception' => $e,
                ], $e->getStatusCode());
            } else {
                if ($e->getStatusCode() == 404) {
                    //backward compatibility old view
                    if (CView::exists('ccore/404')) {
                        return c::response()->view('ccore/404', [], $e->getStatusCode());
                    }
                }
            }
        }

        foreach ($this->renderCallbacks as $renderCallback) {
            if (is_a($e, $this->firstClosureParameterType($renderCallback))) {
                $response = $renderCallback($e, $request);

                if (!is_null($response)) {
                    return $response;
                }
            }
        }

        if ($e instanceof CHTTP_Exception_ResponseException) {
            return $e->getResponse();
        }
        if ($e instanceof CAuth_Exception_AuthenticationException) {
            return $this->unauthenticated($request, $e);
        }
        if ($e instanceof CValidation_Exception) {
            return $this->convertValidationExceptionToResponse($e, $request);
        }

        return $request->expectsJson() ? $this->prepareJsonResponse($request, $e) : $this->prepareResponse($request, $e);
    }

    /**
     * Prepare exception for rendering.
     *
     * @param \Exception $e
     *
     * @return \Exception
     */
    protected function prepareException($e) {
        if ($e instanceof CModel_Exception_ModelNotFoundException) {
            $e = new NotFoundHttpException($e->getMessage(), $e);
        } elseif ($e instanceof CAuth_Exception_AuthorizationException) {
            $e = new AccessDeniedHttpException($e->getMessage(), $e);
        } elseif ($e instanceof CSession_Exception_TokenMismatchException) {
            $e = new CHTTP_Exception_HttpException(419, $e->getMessage(), $e);
        } elseif ($e instanceof SuspiciousOperationException) {
            $e = new NotFoundHttpException('Bad hostname provided.', $e);
        } elseif ($e instanceof PathTraversalDetected) {
            // Flysystem's own path normalizer already refuses the traversal before
            // touching any adapter (local, S3, ...) - this is malformed/malicious
            // input, same class of problem as a bad hostname above, not a server error.
            $e = new NotFoundHttpException('Not Found.', $e);
        }

        return $e;
    }

    /**
     * Map the exception using a registered mapper if possible.
     *
     * @param \Throwable $e
     *
     * @return \Throwable
     */
    protected function mapException($e) {
        if (method_exists($e, 'getInnerException')
            && ($inner = $e->getInnerException()) instanceof Throwable
        ) {
            return $inner;
        }

        foreach ($this->exceptionMap as $class => $mapper) {
            if (is_a($e, $class)) {
                return $mapper($e);
            }
        }

        return $e;
    }

    /**
     * Convert an authentication exception into a response.
     *
     * @param CHTTP_Request                           $request
     * @param CAuth_Exception_AuthenticationException $exception
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function unauthenticated($request, CAuth_Exception_AuthenticationException $exception) {
        return $request->expectsJson() ? c::response()->json(['message' => $exception->getMessage()], 401) : c::redirect()->guest($exception->redirectTo() ? $exception->redirectTo() : c::route('login'));
    }

    /**
     * Create a response object from the given validation exception.
     *
     * @param CValidation_Exception $e
     * @param \CHTTP_Request        $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function convertValidationExceptionToResponse(CValidation_Exception $e, $request) {
        if ($e->response) {
            return $e->response;
        }

        return $request->expectsJson() ? $this->invalidJson($request, $e) : $this->invalid($request, $e);
    }

    /**
     * Convert a validation exception into a response.
     *
     * @param CHTTP_Request         $request
     * @param CValidation_Exception $exception
     *
     * @return CHTTP_Response
     */
    protected function invalid($request, CValidation_Exception $exception) {
        return c::redirect(isset($exception->redirectTo) ? $exception->redirectTo : c::url()->previous())
            ->withInput(carr::except($request->input(), $this->dontFlash))
            ->withErrors($exception->errors(), $exception->errorBag);
    }

    /**
     * Convert a validation exception into a JSON response.
     *
     * @param CHTTP_Request         $request
     * @param CValidation_Exception $exception
     *
     * @return CHttp_JsonResponse
     */
    protected function invalidJson($request, CValidation_Exception $exception) {
        return c::response()->json([
            'errCode' => '422',
            'errMessage' => $exception->getMessage(),
            'data' => [
                'errors' => $exception->errors(),
            ],
        ], $exception->status);
    }

    /**
     * Prepare a response for the given exception.
     *
     * @param \CHTTP_Request $request
     * @param \Exception     $e
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function prepareResponse($request, $e) {
        if (!$this->isHttpException($e) && $this->isDebug()) {
            return $this->toHttpResponse($this->convertExceptionToResponse($e), $e);
        }
        if (!$this->isHttpException($e)) {
            $e = new CHTTP_Exception_HttpException(500, $e->getMessage(), $e);
        }

        $response = $this->toHttpResponse(
            $this->renderHttpException($e),
            $e
        );

        return $response;
    }

    /**
     * Create a Symfony response for the given exception.
     *
     * @param \Exception $e
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function convertExceptionToResponse($e) {
        $response = SymfonyResponse::create(
            $this->renderExceptionContent($e),
            $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500,
            $e instanceof HttpExceptionInterface ? $e->getHeaders() : []
        );

        return $response;
    }

    /**
     * Unwrap the generic HttpException that prepareResponse() creates around a
     * non-HTTP exception.
     *
     * The wrapper is instantiated inside this class, so its getFile(), getLine()
     * and getTrace() point at ExceptionHandler instead of the code that actually
     * threw. Rendering the original keeps the reported location useful. Only the
     * exact CHTTP_Exception_HttpException class is unwrapped, so purpose-built
     * subclasses such as NotFoundHttpException still render as themselves.
     *
     * @param \Throwable $e
     *
     * @return \Throwable
     */
    protected function unwrapWrappedException($e) {
        while (get_class($e) === CHTTP_Exception_HttpException::class && $e->getPrevious() != null) {
            $e = $e->getPrevious();
        }

        return $e;
    }

    /**
     * Get the response content for the given exception.
     *
     * @param \Exception $e
     *
     * @return string
     */
    protected function renderExceptionContent($e) {
        if (CF::isCli()) {
            return $this->renderForConsole(new Symfony\Component\Console\Output\ConsoleOutput(), $e);
        }

        $e = $this->unwrapWrappedException($e);

        try {
            return CException_LegacyExceptionHandler::getContent($e);
            if (CF::isProduction()) {
                if (CView::exists('errors/http/' . '500')) {
                    if (!isset($_GET['show_debug_error'])) {
                        return c::view('errors/http/500', [
                            'exception' => $e,
                        ])->render();
                    }
                }

                return CException_LegacyExceptionHandler::getContent($e);
            }

            $exceptionRenderer = new CException_Renderer_ExceptionRenderer();

            return $exceptionRenderer->render($e);
            //return $this->isDebug() && class_exists(Whoops::class) ? $this->renderExceptionWithWhoops($e) : $this->renderExceptionWithSymfony($e, $this->isDebug());
            //return $this->renderExceptionWithSymfony($e, false);
        } catch (\Throwable $e) {
            return $this->renderExceptionWithLegacy($e);
        } catch (\Exception $e) {
            return $this->renderExceptionWithLegacy($e);
        }
    }

    protected function renderExceptionWithLegacy($e) {
        try {
            return CException_LegacyExceptionHandler::getContent($e);
        } catch (\Throwable $e) {
            return $this->renderExceptionWithSymfony($e, $this->isDebug());
        } catch (\Exception $e) {
            return $this->renderExceptionWithSymfony($e, $this->isDebug());
        }
    }

    /**
     * Render an exception to a string using "Whoops".
     *
     * @param \Exception $e
     *
     * @return string
     */
    protected function renderExceptionWithWhoops($e) {
        return c::tap(new Whoops(), function ($whoops) {
            $whoops->appendHandler($this->whoopsHandler());
            $whoops->writeToOutput(false);
            $whoops->allowQuit(false);
        })->handleException($e);
    }

    /**
     * Get the Whoops handler for the application.
     *
     * @return \Whoops\Handler\Handler
     */
    protected function whoopsHandler() {
        try {
            return new \Whoops\Handler\PrettyPageHandler();
        } catch (CContainer_Exception_BindingResolutionException $e) {
            return (new CException_WhoopsHandler())->forDebug();
        }
    }

    /**
     * Render an exception to a string using Symfony.
     *
     * @param \Exception $e
     * @param bool       $debug
     *
     * @return string
     */
    protected function renderExceptionWithSymfony($e, $debug) {
        $renderer = new HtmlErrorRenderer($debug);

        return $renderer->render($e)->getAsString();
    }

    /**
     * Render the given HttpException.
     *
     * @param \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function renderHttpException(Exception $e) {
        $this->registerErrorViewPaths();

        if ($view = $this->getHttpExceptionView($e)) {
            try {
                return c::response()->view($view, [
                    'errors' => new CBase_ViewErrorBag(),
                    'exception' => $e,
                ], $e->getStatusCode(), $e->getHeaders());
            } catch (Throwable $t) {
                if (CF::config('app.debug')) {
                    throw $t;
                }

                $this->report($t);
            }
        }

        return $this->convertExceptionToResponse($e);
    }

    /**
     * Register the error template hint paths.
     *
     * @return void
     */
    protected function registerErrorViewPaths() {
        return c::collect(CF::paths())->map(function ($path) {
            return $path . 'views';
        });

        $paths = c::collect(CF::paths());
        c::view()->replaceNamespace('errors', $paths->map(function ($path) {
            return "{$path}/errors";
        })->push(__DIR__ . '/views')->all());
    }

    /**
     * Get the view used to render HTTP exceptions.
     *
     * @param \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e
     *
     * @return null|string
     */
    protected function getHttpExceptionView(HttpExceptionInterface $e) {
        $view = 'errors/http/' . $e->getStatusCode();

        if (c::view()->exists($view)) {
            return $view;
        }

        $view = substr($view, 0, -2) . 'xx';

        if (c::view()->exists($view)) {
            return $view;
        }

        return null;
    }

    /**
     * Map the given exception into an http response.
     *
     * @param \Symfony\Component\HttpFoundation\Response $response
     * @param \Exception                                 $e
     *
     * @return CHTTP_Response
     */
    protected function toHttpResponse($response, $e) {
        if ($response instanceof SymfonyRedirectResponse) {
            $response = new CHTTP_RedirectResponse(
                $response->getTargetUrl(),
                $response->getStatusCode(),
                $response->headers->all()
            );
            $response->setRequest(c::request());
        } else {
            $response = new CHTTP_Response(
                $response->getContent(),
                $response->getStatusCode(),
                $response->headers->all()
            );
        }

        return $response->withException($e);
    }

    /**
     * Prepare a JSON response for the given exception.
     *
     * @param CHTTP_Request $request
     * @param \Exception    $e
     *
     * @return CHTTP_JsonResponse
     */
    protected function prepareJsonResponse($request, $e) {
        return new CHTTP_JsonResponse(
            $request->isCresRequest() ? $this->convertExceptionToCresArray($e) : $this->convertExceptionToArray($e),
            $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500,
            $e instanceof HttpExceptionInterface ? $e->getHeaders() : [],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Convert the given exception to an array.
     *
     * @param \Exception $e
     *
     * @return array
     */
    protected function convertExceptionToArray($e) {
        $result = [
            'message' => $this->isHttpException($e) ? $e->getMessage() : 'Server Error',
        ];
        if ($this->isDebug()) {
            $trace = c::collect($e->getTrace())->map(function ($trace) {
                return carr::except($trace, ['args']);
            })->all();
            $result = [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $trace,
            ];
        }

        return $result;
    }

    /**
     * Convert the given exception to an array.
     *
     * @param \Exception $e
     *
     * @return array
     */
    protected function convertExceptionToCresArray($e) {
        $errMessage = $e->getMessage();
        if (!$errMessage && $e instanceof NotFoundHttpException) {
            $errMessage = 'Page Not Found';
        }
        if (!$errMessage) {
            $errMessage = 'Something went wrong...';
        }
        $result = [
            'errCode' => $e->getCode(),
            'errMessage' => $errMessage,
            'data' => null,
        ];

        if ($this->isDebug()) {
            $trace = c::collect($e->getTrace())->map(function ($trace) {
                return carr::except($trace, ['args']);
            })->all();
            $result = [
                'errCode' => $e->getCode(),
                'errMessage' => $errMessage,
                'data' => [
                    'message' => $errMessage,
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $trace,
                ]
            ];
        }

        return $result;
    }

    protected function isDebug() {
        $isDebug = CF::config('app.debug');
        if ($isDebug === null) {
            $isDebug = !CF::isProduction();
        }

        return $isDebug;
    }

    /**
     * Render an exception to the console.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \Exception                                        $e
     *
     * @return void
     */
    public function renderForConsole($output, $e) {
        (new ConsoleApplication())->renderException($e, $output);
    }

    /**
     * Determine if the given exception is an HTTP exception.
     *
     * @param \Exception $e
     *
     * @return bool
     */
    protected function isHttpException($e) {
        return $e instanceof HttpExceptionInterface;
    }
}
