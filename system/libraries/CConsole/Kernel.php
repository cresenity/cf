<?php

use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Symfony\Component\Console\Application as ConsoleApplication;

class CConsole_Kernel implements CConsole_KernelInterface {
    /**
     * The event dispatcher implementation.
     *
     * @var CEvents_DispatcherInterface
     */
    protected $events;

    /**
     * The Symfony event dispatcher implementation.
     *
     * @var null|\Symfony\Contracts\EventDispatcher\EventDispatcherInterface
     */
    protected $symfonyDispatcher;

    /**
     * The Artisan application instance.
     *
     * @var CConsole_Application
     */
    protected $cfCli;

    /**
     * The CFCli commands provided by the application.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * Indicates if the Closure commands have been loaded.
     *
     * @var bool
     */
    protected $commandsLoaded = false;

    /**
     * The bootstrap classes for the application.
     *
     * @var string[]
     */
    protected $bootstrappers = [

    ];

    /**
     * Create a new console kernel instance.
     *
     * @param CEvent_Dispatcher $events
     *
     * @return void
     */
    public function __construct(?CEvent_Dispatcher $events = null) {
        if (!defined('CFCLI_BINARY')) {
            define('CFCLI_BINARY', 'cf');
        }

        if ($events == null) {
            $events = CEvent::dispatcher();
        }
        $this->events = $events;

        CF::booted(function () {
            if (!CF::isTesting()) {
                $this->rerouteSymfonyCommandEvents();
            }
            $this->defineConsoleSchedule();
        });
    }

    /**
     * Re-route the Symfony command events to their Laravel counterparts.
     *
     * @internal
     *
     * @return $this
     */
    public function rerouteSymfonyCommandEvents() {
        if (is_null($this->symfonyDispatcher)) {
            $this->symfonyDispatcher = new EventDispatcher();

            $this->symfonyDispatcher->addListener(ConsoleEvents::COMMAND, function (ConsoleCommandEvent $event) {
                $this->events->dispatch(
                    new CConsole_Event_CommandStarting($event->getCommand()->getName(), $event->getInput(), $event->getOutput())
                );
            });

            $this->symfonyDispatcher->addListener(ConsoleEvents::TERMINATE, function (ConsoleTerminateEvent $event) {
                $this->events->dispatch(
                    new CConsole_Event_CommandFinished($event->getCommand()->getName(), $event->getInput(), $event->getOutput(), $event->getExitCode())
                );
            });
        }

        return $this;
    }

    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function defineConsoleSchedule() {
        $this->schedule(CCron::schedule());
    }

    /**
     * Run the console application.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    public function handle($input, $output = null) {
        try {
            $this->bootstrap();

            return $this->getCFCli()->run($input, $output);
        } catch (Throwable $e) {
            $this->reportException($e);

            try {
                $this->renderException($output, $e);
            } catch (Throwable $renderException) {
                // broken console output must not mask $e, already reported above.
            }

            return 1;
        } catch (Exception $e) {
            $this->reportException($e);

            try {
                $this->renderException($output, $e);
            } catch (Throwable $renderException) {
                // broken console output must not mask $e, already reported above.
            }

            return 1;
        }
    }

    /**
     * Terminate the application.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param int                                             $status
     *
     * @return void
     */
    public function terminate($input, $status) {
        CF::terminate();
    }

    /**
     * Define the application's command schedule.
     *
     * @param \CCron_Schedule $schedule
     *
     * @return void
     */
    protected function schedule(CCron_Schedule $schedule) {
    }

    /**
     * Get the timezone that should be used by default for scheduled events.
     *
     * @return null|\DateTimeZone|string
     */
    protected function scheduleTimezone() {
        return CF::config('app.schedule_timezone', CF::config('app.timezone'));
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands() {
    }

    /**
     * Register a Closure based command with the application.
     *
     * @param string   $signature
     * @param \Closure $callback
     *
     * @return \CConsole_ClosureCommand
     */
    public function command($signature, Closure $callback) {
        $command = new CConsole_ClosureCommand($signature, $callback);

        CConsole_Application::starting(function ($cfCli) use ($command) {
            $cfCli->add($command);
        });

        return $command;
    }

    /**
     * Register all of the commands in the given directory.
     *
     * @param array|string $paths
     *
     * @return void
     */
    protected function load($paths) {
        $paths = array_unique(carr::wrap($paths));

        $paths = array_filter($paths, function ($path) {
            return is_dir($path);
        });

        if (empty($paths)) {
            return;
        }

        // $namespace = $this->app->getNamespace();

        // foreach ((new Finder())->in($paths)->files() as $command) {
        //     $command = $namespace . str_replace(
        //         ['/', '.php'],
        //         ['\\', ''],
        //         cstr::after($command->getPathname(), app_path() . DIRECTORY_SEPARATOR)
        //     );

        //     if (is_subclass_of($command, CConsole_Command::class)
        //         && !(new ReflectionClass($command))->isAbstract()
        //     ) {
        //         CConsole_Application::starting(function ($artisan) use ($command) {
        //             $artisan->resolve($command);
        //         });
        //     }
        // }
    }

    /**
     * Register the given command with the console application.
     *
     * @param \Symfony\Component\Console\Command\Command $command
     *
     * @return void
     */
    public function registerCommand($command) {
        $this->getCFCli()->add($command);
    }

    /**
     * Run an Artisan console command by name.
     *
     * @param string                                            $command
     * @param array                                             $parameters
     * @param \Symfony\Component\Console\Output\OutputInterface $outputBuffer
     *
     * @return int
     */
    public function call($command, array $parameters = [], $outputBuffer = null) {
        $this->bootstrap();

        return $this->getCFCli()->call($command, $parameters, $outputBuffer);
    }

    /**
     * Queue the given console command.
     *
     * @param string $command
     * @param array  $parameters
     *
     * @return \CQueue_PendingDispatch
     */
    public function queue($command, array $parameters = []) {
        return CConsole_QueuedCommand::dispatch(func_get_args());
    }

    /**
     * Get all of the commands registered with the console.
     *
     * @return array
     */
    public function all() {
        $this->bootstrap();

        return $this->getCFCli()->all();
    }

    /**
     * Get the output for the last run command.
     *
     * @return string
     */
    public function output() {
        $this->bootstrap();

        return $this->getCFCli()->output();
    }

    /**
     * Bootstrap the application for artisan commands.
     *
     * @return void
     */
    public function bootstrap() {
        //        if (!$this->app->hasBeenBootstrapped()) {
        //            $this->app->bootstrapWith($this->bootstrappers());
        //        }
        //
        //        $this->app->loadDeferredProviders();

        if (!$this->commandsLoaded) {
            $this->commands();

            $this->commandsLoaded = true;
        }
    }

    /**
     * Get the CConsole application instance.
     *
     * @return CConsole_Application
     */
    protected function getCFCli() {
        if (is_null($this->cfCli)) {
            $this->cfCli = (new CConsole_Application())
                ->resolveCommands($this->commands)
                ->setContainerCommandLoader();

            if ($this->symfonyDispatcher instanceof EventDispatcher) {
                $this->cfCli->setDispatcher($this->symfonyDispatcher);
            }
        }

        return $this->cfCli;
    }

    /**
     * Get the CConsole application instance.
     *
     * @return CConsole_Application
     */
    public function cfCli() {
        return $this->getCFCli();
    }

    /**
     * Set the CF CLI application instance.
     *
     * @param CConsole_Application $cfCli
     *
     * @return void
     */
    public function setCFCli($cfCli) {
        $this->cfCli = $cfCli;
    }

    /**
     * Get the bootstrap classes for the application.
     *
     * @return array
     */
    protected function bootstrappers() {
        return $this->bootstrappers;
    }

    /**
     * Report the exception to the exception handler.
     *
     * @param \Exception|\Throwable $e
     *
     * @return void
     */
    protected function reportException($e) {
        CException::exceptionHandler()->report($e);
    }

    /**
     * Report the exception to the exception handler.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \Exception|\Throwable                             $e
     *
     * @return void
     */
    protected function renderException($output, $e) {
        CException::exceptionHandler()->renderForConsole($output, $e);
    }
}
