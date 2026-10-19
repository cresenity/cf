<?php

use Symfony\Component\Console\Output\OutputInterface;

class CCron_Runner {
    protected $eventsRan;

    protected $startedAt;

    protected static $shutdownReporterRegistered = false;

    public function run(?OutputInterface $output = null) {
        static::registerShutdownReporter();
        $this->eventsRan = false;
        $this->startedAt = c::now();
        foreach (c::cron()->dueEvents() as $event) {
            /** @var CCron_Event $event */
            if (!$event->filtersPass()) {
                CEvent::dispatch(new CCron_Event_ScheduledTaskSkipped($event));

                continue;
            }

            if ($event->onOneServer) {
                $this->runSingleServerEvent($event);
            } else {
                $this->runEvent($event);
            }

            $this->eventsRan = true;
        }

        if (!$this->eventsRan) {
            if ($output != null) {
                $output->writeln('No scheduled commands are ready to run.');
            }
        }
    }

    /**
     * Run the given single server event.
     *
     * @param \CCron_Event $event
     *
     * @return void
     */
    protected function runSingleServerEvent($event, ?OutputInterface $output = null) {
        if (c::cron()->serverShouldRun($event, $this->startedAt)) {
            $this->runEvent($event);
        } else {
            if ($output) {
                $output->writeln('<info>Skipping command (has already run on another server):</info> ' . $event->getSummaryForDisplay());
            } else {
                $event->log('Skipping command (has already run on another server): ' . $event->getSummaryForDisplay());
            }
        }
    }

    /**
     * Run the given event.
     *
     * @param \CCron_Event $event
     *
     * @return void
     */
    protected function runEvent($event, ?OutputInterface $output = null) {
        CCron::setEvent($event);
        if ($output) {
            $output->writeln('<info>[' . date('c') . '] Running scheduled command:</info> ' . $event->getSummaryForDisplay());
        }

        CEvent::dispatch(new CCron_Event_ScheduledTaskStarting($event));
        $start = microtime(true);

        try {
            $event->log('Starting scheduled job : ' . $event->getSummaryForDisplay());
            $event->run();
            $runtime = round(microtime(true) - $start, 2);
            $event->log('Ended scheduled job : ' . $event->getSummaryForDisplay() . " [{$runtime}s]");

            CEvent::dispatch(new CCron_Event_ScheduledTaskFinished(
                $event,
                $runtime
            ));

            $this->eventsRan = true;
        } catch (Throwable $e) {
            CEvent::dispatch(new CCron_Event_ScheduledTaskFailed($event, $e));
            CException::exceptionHandler()->report($e);
            $this->writeError($event, $e);
        } catch (Exception $e) {
            CEvent::dispatch(new CCron_Event_ScheduledTaskFailed($event, $e));
            CException::exceptionHandler()->report($e);
            $this->writeError($event, $e);
        }
        CCron::unsetEvent();
    }

    /**
     * Catat event yang membuat proses berhenti sebelum selesai (fatal, exit, die), yang tidak tertangkap try/catch di runEvent().
     *
     * @return void
     */
    protected static function registerShutdownReporter() {
        if (static::$shutdownReporterRegistered) {
            return;
        }
        static::$shutdownReporterRegistered = true;
        register_shutdown_function([static::class, 'reportDiedEvent']);
    }

    /**
     * @return void
     */
    public static function reportDiedEvent() {
        $event = CCron::getRunningEvent();
        if ($event === null) {
            return;
        }
        $error = error_get_last();
        $isFatal = $error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE]);
        $reason = $isFatal
            ? $error['message'] . ' (' . $error['file'] . ':' . $error['line'] . ')'
            : 'proses berhenti sebelum job selesai (exit/die)';

        try {
            $event->log('Died scheduled job : ' . $event->getSummaryForDisplay() . ' - ' . $reason);
            // Fatal sudah dilaporkan HandleExceptionBootstrapper::handleShutdown(), exit/die tidak dilaporkan siapa pun.
            if (!$isFatal) {
                CException::exceptionHandler()->report(new RuntimeException('Cron event berhenti sebelum selesai: ' . $event->getSummaryForDisplay()));
            }
        } catch (Throwable $e) {
        }
    }

    private function writeError($event, $exception) {
        $event->log();
        $event->log(str_repeat('#', 64));
        $event->log('# Error : ' . $exception->getMessage() . "\n# " . $exception->getFile() . ' (' . $exception->getLine() . ')');
        $event->log($exception->getTraceAsString());
        $event->log(str_repeat('#', 64));
        $event->log();
    }
}
