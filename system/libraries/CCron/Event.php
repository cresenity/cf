<?php

use Cron\CronExpression;
use GuzzleHttp\Client as HttpClient;
use Symfony\Component\Process\Process;
use GuzzleHttp\Exception\TransferException;
use Psr\Http\Client\ClientExceptionInterface;

class CCron_Event {
    use CTrait_Macroable;
    use CCron_Trait_ManagesFrequenciesTrait;
    use CTrait_ReflectsClosureTrait;

    /**
     * The command string.
     *
     * @var string
     */
    public $command;

    /**
     * The cron expression representing the event's frequency.
     *
     * @var string
     */
    public $expression = '* * * * *';

    /**
     * The timezone the date should be evaluated on.
     *
     * @var \DateTimeZone|string
     */
    public $timezone;

    /**
     * The user the command should run as.
     *
     * @var string
     */
    public $user;

    /**
     * The list of environments the command should run under.
     *
     * @var array
     */
    public $environments = [];

    /**
     * Indicates if the command should run in maintenance mode.
     *
     * @var bool
     */
    public $evenInMaintenanceMode = false;

    /**
     * Indicates if the command should not overlap itself.
     *
     * @var bool
     */
    public $withoutOverlapping = false;

    /**
     * Indicates if the command should only be allowed to run on one server for each cron expression.
     *
     * @var bool
     */
    public $onOneServer = false;

    /**
     * The amount of time the mutex should be valid.
     *
     * @var int
     */
    public $expiresAt = 1440;

    /**
     * Indicates if the command should run in the background.
     *
     * @var bool
     */
    public $runInBackground = false;

    /**
     * The location that output should be sent to.
     *
     * @var string
     */
    public $output = '/dev/null';

    /**
     * Indicates whether output should be appended.
     *
     * @var bool
     */
    public $shouldAppendOutput = false;

    /**
     * The human readable description of the event.
     *
     * @var string
     */
    public $description;

    /**
     * The event mutex implementation.
     *
     * @var \CCron_Contract_EventMutexInterface
     */
    public $mutex;

    /**
     * The exit status code of the command.
     *
     * @var null|int
     */
    public $exitCode;

    /**
     * The array of filter callbacks.
     *
     * @var array
     */
    protected $filters = [];

    /**
     * The array of reject callbacks.
     *
     * @var array
     */
    protected $rejects = [];

    /**
     * The array of callbacks to be run before the event is started.
     *
     * @var array
     */
    protected $beforeCallbacks = [];

    /**
     * The array of callbacks to be run after the event is finished.
     *
     * @var array
     */
    protected $afterCallbacks = [];

    /**
     * Handle for log() method,.
     *
     * @see CCron_Event::log()
     *
     * @var stream
     */
    private static $logHandle = false;

    /**
     * Create a new event instance.
     *
     * @param \CCron_Contract_EventMutexInterface $mutex
     * @param string                              $command
     * @param null|\DateTimeZone|string           $timezone
     *
     * @return void
     */
    public function __construct(CCron_Contract_EventMutexInterface $mutex, $command, $timezone = null) {
        $this->mutex = $mutex;
        $this->command = $command;
        $this->timezone = $timezone;

        $this->output = $this->getDefaultOutput();
    }

    /**
     * Get the default output depending on the OS.
     *
     * @return string
     */
    public function getDefaultOutput() {
        return (DIRECTORY_SEPARATOR === '\\') ? 'NUL' : '/dev/null';
    }

    /**
     * Run the given event.
     *
     * @return void
     */
    public function run() {
        if ($this->withoutOverlapping
            && !$this->mutex->create($this)
        ) {
            return;
        }
        $this->runInBackground
            ? $this->runCommandInBackground()
            : $this->runCommandInForeground();
    }

    /**
     * Get the mutex name for the scheduled command.
     *
     * @return string
     */
    public function mutexName() {
        return 'framework' . DIRECTORY_SEPARATOR . 'schedule-' . sha1($this->expression . $this->command);
    }

    /**
     * Run the command in the foreground.
     *
     * @return void
     */
    protected function runCommandInForeground() {
        try {
            $this->callBeforeCallbacks();

            $this->exitCode = Process::fromShellCommandline(
                $this->buildCommand(),
                DOCROOT,
                null,
                null,
                null
            )->run();

            $this->callAfterCallbacks();
        } finally {
            $this->removeMutex();
        }
    }

    /**
     * Run the command in the background.
     *
     * @return void
     */
    protected function runCommandInBackground() {
        try {
            $this->callBeforeCallbacks();

            Process::fromShellCommandline($this->buildCommand(), DOCROOT, null, null, null)->run();
        } catch (Throwable $exception) {
            $this->removeMutex();

            throw $exception;
        }
    }

    /**
     * Call all of the "before" callbacks for the event.
     *
     * @return void
     */
    public function callBeforeCallbacks() {
        foreach ($this->beforeCallbacks as $callback) {
            $container = c::container();
            /** @var CContainer_Container $container */
            $container->call($callback);
        }
    }

    /**
     * Call all of the "after" callbacks for the event.
     *
     * @return void
     */
    public function callAfterCallbacks() {
        foreach ($this->afterCallbacks as $callback) {
            $container = c::container();
            /** @var CContainer_Container $container */
            $container->call($callback);
        }
    }

    /**
     * Call all of the "after" callbacks for the event.
     *
     * @param int $exitCode
     *
     * @return void
     */
    public function callAfterCallbacksWithExitCode($exitCode) {
        $this->exitCode = (int) $exitCode;

        try {
            $this->callAfterCallbacks();
        } finally {
            $this->removeMutex();
        }
    }

    /**
     * Build the command string.
     *
     * @return string
     */
    public function buildCommand() {
        return (new CCron_CommandBuilder())->buildCommand($this);
    }

    /**
     * Determine if the given event should run based on the Cron expression.
     *
     * @return bool
     */
    public function isDue() {
        if (!$this->runsInMaintenanceMode() && CF::isDownForMaintenance()) {
            return false;
        }

        return $this->expressionPasses()
               && $this->runsInEnvironment(CF::environment());
    }

    /**
     * Determine if the event runs in maintenance mode.
     *
     * @return bool
     */
    public function runsInMaintenanceMode() {
        return $this->evenInMaintenanceMode;
    }

    /**
     * Determine if the Cron expression passes.
     *
     * @return bool
     */
    protected function expressionPasses() {
        $date = c::now();

        if ($this->timezone) {
            $date = $date->setTimezone($this->timezone);
        }

        return (new CronExpression($this->expression))->isDue($date->toDateTimeString());
    }

    /**
     * Determine if the event runs in the given environment.
     *
     * @param string $environment
     *
     * @return bool
     */
    public function runsInEnvironment($environment) {
        return empty($this->environments) || in_array($environment, $this->environments);
    }

    /**
     * Determine if the filters pass for the event.
     *
     * @return bool
     */
    public function filtersPass() {
        foreach ($this->filters as $callback) {
            if (!c::container()->call($callback)) {
                return false;
            }
        }

        foreach ($this->rejects as $callback) {
            if (c::container()->call($callback)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ensure that the output is stored on disk in a log file.
     *
     * @return $this
     */
    public function storeOutput() {
        $this->ensureOutputIsBeingCaptured();

        return $this;
    }

    /**
     * Send the output of the command to a given location.
     *
     * @param string $location
     * @param bool   $append
     *
     * @return $this
     */
    public function sendOutputTo($location, $append = false) {
        $this->output = $location;

        $this->shouldAppendOutput = $append;

        return $this;
    }

    /**
     * Append the output of the command to a given location.
     *
     * @param string $location
     *
     * @return $this
     */
    public function appendOutputTo($location) {
        return $this->sendOutputTo($location, true);
    }

    /**
     * E-mail the results of the scheduled operation.
     *
     * @param array|mixed $addresses
     * @param bool        $onlyIfOutputExists
     *
     * @throws \LogicException
     *
     * @return $this
     */
    public function emailOutputTo($addresses, $onlyIfOutputExists = false) {
        $this->ensureOutputIsBeingCaptured();

        $addresses = carr::wrap($addresses);

        return $this->then(function (CEmail_Sender $mailer) use ($addresses, $onlyIfOutputExists) {
            $this->emailOutput($mailer, $addresses, $onlyIfOutputExists);
        });
    }

    /**
     * E-mail the results of the scheduled operation if it produces output.
     *
     * @param array|mixed $addresses
     *
     * @throws \LogicException
     *
     * @return $this
     */
    public function emailWrittenOutputTo($addresses) {
        return $this->emailOutputTo($addresses, true);
    }

    /**
     * E-mail the results of the scheduled operation if it fails.
     *
     * @param array|mixed $addresses
     *
     * @return $this
     */
    public function emailOutputOnFailure($addresses) {
        $this->ensureOutputIsBeingCaptured();

        $addresses = carr::wrap($addresses);

        return $this->onFailure(function (CEmail_Sender $mailer) use ($addresses) {
            $this->emailOutput($mailer, $addresses, false);
        });
    }

    /**
     * Ensure that the command output is being captured.
     *
     * @return void
     */
    protected function ensureOutputIsBeingCaptured() {
        if (is_null($this->output) || $this->output == $this->getDefaultOutput()) {
            $path = DOCROOT . 'temp' . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . CF::appCode() . DIRECTORY_SEPARATOR . 'schedule-' . sha1($this->mutexName()) . '.log';
            if (!CFile::isDirectory(CFile::dirname($path))) {
                CFile::makeDirectory(CFile::dirname($path), 0755, true);
            }
            $this->sendOutputTo($path);
        }
    }

    /**
     * E-mail the output of the event to the recipients.
     *
     * @param \CEmail_Sender $mailer
     * @param array          $addresses
     * @param bool           $onlyIfOutputExists
     *
     * @return void
     */
    protected function emailOutput(CEmail_Sender $mailer, $addresses, $onlyIfOutputExists = false) {
        $text = is_file($this->output) ? file_get_contents($this->output) : '';

        if ($onlyIfOutputExists && empty($text)) {
            return;
        }

        // $mailer->raw($text, function ($m) use ($addresses) {
        //     $m->to($addresses)->subject($this->getEmailSubject());
        // });
    }

    /**
     * Get the e-mail subject line for output results.
     *
     * @return string
     */
    protected function getEmailSubject() {
        if ($this->description) {
            return $this->description;
        }

        return "Scheduled Job Output For [{$this->command}]";
    }

    /**
     * Register a callback to ping a given URL before the job runs.
     *
     * @param string $url
     *
     * @return $this
     */
    public function pingBefore($url) {
        return $this->before($this->pingCallback($url));
    }

    /**
     * Register a callback to ping a given URL before the job runs if the given condition is true.
     *
     * @param bool   $value
     * @param string $url
     *
     * @return $this
     */
    public function pingBeforeIf($value, $url) {
        return $value ? $this->pingBefore($url) : $this;
    }

    /**
     * Register a callback to ping a given URL after the job runs.
     *
     * @param string $url
     *
     * @return $this
     */
    public function thenPing($url) {
        return $this->then($this->pingCallback($url));
    }

    /**
     * Register a callback to ping a given URL after the job runs if the given condition is true.
     *
     * @param bool   $value
     * @param string $url
     *
     * @return $this
     */
    public function thenPingIf($value, $url) {
        return $value ? $this->thenPing($url) : $this;
    }

    /**
     * Register a callback to ping a given URL if the operation succeeds.
     *
     * @param string $url
     *
     * @return $this
     */
    public function pingOnSuccess($url) {
        return $this->onSuccess($this->pingCallback($url));
    }

    /**
     * Register a callback to ping a given URL if the operation fails.
     *
     * @param string $url
     *
     * @return $this
     */
    public function pingOnFailure($url) {
        return $this->onFailure($this->pingCallback($url));
    }

    /**
     * Get the callback that pings the given URL.
     *
     * @param string $url
     *
     * @return \Closure
     */
    protected function pingCallback($url) {
        return function (CContainer_ContainerInterface $container, HttpClient $http) use ($url) {
            try {
                $http->request('GET', $url);
            } catch (ClientExceptionInterface $e) {
                $container->make(ExceptionHandler::class)->report($e);
            } catch (TransferException $e) {
                $container->make(ExceptionHandler::class)->report($e);
            }
        };
    }

    /**
     * State that the command should run in the background.
     *
     * @return $this
     */
    public function runInBackground() {
        $this->runInBackground = true;

        return $this;
    }

    /**
     * Set which user the command should run as.
     *
     * @param string $user
     *
     * @return $this
     */
    public function user($user) {
        $this->user = $user;

        return $this;
    }

    /**
     * Limit the environments the command should run in.
     *
     * @param array|mixed $environments
     *
     * @return $this
     */
    public function environments($environments) {
        $this->environments = is_array($environments) ? $environments : func_get_args();

        return $this;
    }

    /**
     * State that the command should run even in maintenance mode.
     *
     * @return $this
     */
    public function evenInMaintenanceMode() {
        $this->evenInMaintenanceMode = true;

        return $this;
    }

    /**
     * Do not allow the event to overlap each other.
     *
     * @param int $expiresAt
     *
     * @return $this
     */
    public function withoutOverlapping($expiresAt = 1440) {
        $this->withoutOverlapping = true;

        $this->expiresAt = $expiresAt;

        return $this->skip(function () {
            return $this->mutex->exists($this);
        });
    }

    /**
     * Allow the event to only run on one server for each cron expression.
     *
     * @return $this
     */
    public function onOneServer() {
        $this->onOneServer = true;

        return $this;
    }

    /**
     * Register a callback to further filter the schedule.
     *
     * @param \Closure|bool $callback
     *
     * @return $this
     */
    public function when($callback) {
        $this->filters[] = CBase_Reflector::isCallable($callback) ? $callback : function () use ($callback) {
            return $callback;
        };

        return $this;
    }

    /**
     * Register a callback to further filter the schedule.
     *
     * @param \Closure|bool $callback
     *
     * @return $this
     */
    public function skip($callback) {
        $this->rejects[] = CBase_Reflector::isCallable($callback) ? $callback : function () use ($callback) {
            return $callback;
        };

        return $this;
    }

    /**
     * Register a callback to be called before the operation.
     *
     * @param \Closure $callback
     *
     * @return $this
     */
    public function before(Closure $callback) {
        $this->beforeCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register a callback to be called after the operation.
     *
     * @param \Closure $callback
     *
     * @return $this
     */
    public function after(Closure $callback) {
        return $this->then($callback);
    }

    /**
     * Register a callback to be called after the operation.
     *
     * @param \Closure $callback
     *
     * @return $this
     */
    public function then(Closure $callback) {
        $parameters = $this->closureParameterTypes($callback);

        if (carr::get($parameters, 'output') === Stringable::class) {
            return $this->thenWithOutput($callback);
        }

        $this->afterCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register a callback that uses the output after the job runs.
     *
     * @param \Closure $callback
     * @param bool     $onlyIfOutputExists
     *
     * @return $this
     */
    public function thenWithOutput(Closure $callback, $onlyIfOutputExists = false) {
        $this->ensureOutputIsBeingCaptured();

        return $this->then($this->withOutputCallback($callback, $onlyIfOutputExists));
    }

    /**
     * Register a callback to be called if the operation succeeds.
     *
     * @param \Closure $callback
     *
     * @return $this
     */
    public function onSuccess(Closure $callback) {
        $parameters = $this->closureParameterTypes($callback);

        if (carr::get($parameters, 'output') === Stringable::class) {
            return $this->onSuccessWithOutput($callback);
        }

        return $this->then(function () use ($callback) {
            if (0 === $this->exitCode) {
                c::container()->call($callback);
            }
        });
    }

    /**
     * Register a callback that uses the output if the operation succeeds.
     *
     * @param \Closure $callback
     * @param bool     $onlyIfOutputExists
     *
     * @return $this
     */
    public function onSuccessWithOutput(Closure $callback, $onlyIfOutputExists = false) {
        $this->ensureOutputIsBeingCaptured();

        return $this->onSuccess($this->withOutputCallback($callback, $onlyIfOutputExists));
    }

    /**
     * Register a callback to be called if the operation fails.
     *
     * @param \Closure $callback
     *
     * @return $this
     */
    public function onFailure(Closure $callback) {
        $parameters = $this->closureParameterTypes($callback);

        if (carr::get($parameters, 'output') === Stringable::class) {
            return $this->onFailureWithOutput($callback);
        }

        return $this->then(function () use ($callback) {
            if (0 !== $this->exitCode) {
                c::container()->call($callback);
            }
        });
    }

    /**
     * Register a callback that uses the output if the operation fails.
     *
     * @param \Closure $callback
     * @param bool     $onlyIfOutputExists
     *
     * @return $this
     */
    public function onFailureWithOutput(Closure $callback, $onlyIfOutputExists = false) {
        $this->ensureOutputIsBeingCaptured();

        return $this->onFailure($this->withOutputCallback($callback, $onlyIfOutputExists));
    }

    /**
     * Get a callback that provides output.
     *
     * @param \Closure $callback
     * @param bool     $onlyIfOutputExists
     *
     * @return \Closure
     */
    protected function withOutputCallback(Closure $callback, $onlyIfOutputExists = false) {
        return function () use ($callback, $onlyIfOutputExists) {
            $output = $this->output && is_file($this->output) ? file_get_contents($this->output) : '';

            return $onlyIfOutputExists && empty($output)
                            ? null
                            : c::container()->call($callback, ['output' => new Stringable($output)]);
        };
    }

    /**
     * Set the human-friendly description of the event.
     *
     * @param string $description
     *
     * @return $this
     */
    public function name($description) {
        return $this->description($description);
    }

    /**
     * Set the human-friendly description of the event.
     *
     * @param string $description
     *
     * @return $this
     */
    public function description($description) {
        $this->description = $description;

        return $this;
    }

    /**
     * Get the summary of the event for display.
     *
     * @return string
     */
    public function getSummaryForDisplay() {
        if (is_string($this->description)) {
            return $this->description;
        }

        return $this->buildCommand();
    }

    /**
     * Determine the next due date for an event.
     *
     * @param \DateTimeInterface|string $currentTime
     * @param int                       $nth
     * @param bool                      $allowCurrentDate
     *
     * @return \CCarbon
     */
    public function nextRunDate($currentTime = 'now', $nth = 0, $allowCurrentDate = false) {
        if ($currentTime === 'now') {
            // lewat CCarbon supaya setTestNow() ikut berlaku
            $currentTime = CCarbon::now();
        }

        return CCarbon::instance((new CronExpression($this->getExpression()))
            ->getNextRunDate($currentTime, $nth, $allowCurrentDate, $this->timezone));
    }

    /**
     * Get the Cron expression for the event.
     *
     * @return string
     */
    public function getExpression() {
        return $this->expression;
    }

    /**
     * Set the event mutex implementation to be used.
     *
     * @param \CCron_Contract_EventMutexInterface $mutex
     *
     * @return $this
     */
    public function preventOverlapsUsing(CCron_Contract_EventMutexInterface $mutex) {
        $this->mutex = $mutex;

        return $this;
    }

    /**
     * Delete the mutex for the event.
     *
     * @return void
     */
    protected function removeMutex() {
        if ($this->withoutOverlapping) {
            $this->mutex->forget($this);
        }
    }

    public function getName() {
        $eventName = $this->description;
        $eventName = str_replace(' ', '', $eventName);

        return $eventName;
    }

    public function getLogDirectory() {
        $logDir = DOCROOT . 'data' . DS . 'cron' . DS . CF::appCode() . DS . 'log' . DS . $this->getName();

        return $logDir;
    }

    public function getLogFile() {
        return $this->getLogDirectory() . DS . $this->getName() . '.log';
    }

    /**
     * Write event log into file.
     *
     * @param string $message
     *
     * @return void
     */
    public function log($message = '') {
        $header = "\nDate                  Message\n";
        $date = date('Y-m-d H:i:s');
        $prefix = "[{$date}]" . str_repeat("\t", $indent = 0);
        $logFile = $this->getLogFile();
        if (!file_exists($this->getLogDirectory())) {
            mkdir($this->getLogDirectory(), 0777, true);
        }
        $this->autoRotateLog();
        if (!CFile::exists($logFile)) {
            CFile::put($logFile, $header);
        }
        $line = $prefix . ' ' . str_replace("\n", "\n{$prefix} ", trim($message)) . "\n";
        CFile::append($logFile, $line);
    }

    public function autoRotateLog($size = null, $keep = null) {
        $logFile = $this->getLogFile();
        $size = $size ?: CF::config('cron.logs.rotation.size', 500 * 1024);
        $keep = $keep ?: CF::config('cron.logs.rotation.keep', 10);
        if (strlen($logFile) > 0 && CFile::isFile($logFile) && CFile::size($logFile) > $size) {
            $rotator = CLogger_Rotator::createRotate($logFile);
            $rotator->size($size)->keep($keep)->run();
        }
    }

    public function rotateLog() {
        $logFile = $this->getLogFile();

        if (strlen($logFile) > 0 && CFile::isFile($logFile)) {
            $rotator = CLogger_Rotator::createRotate($logFile);
            $rotator->forceRotate();
        }
    }
}
