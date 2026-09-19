<?php

/**
 * Menjalankan command phpcf dari test dengan ekspektasi output/pertanyaan ala CTesting_PendingCommand.
 */
trait CTesting_Concern_InteractsWithConsole {
    /**
     * @var bool
     */
    public $mockConsoleOutput = true;

    /**
     * @var array
     */
    public $expectedOutput = [];

    /**
     * @var array
     */
    public $expectedOutputSubstrings = [];

    /**
     * @var array
     */
    public $unexpectedOutput = [];

    /**
     * @var array
     */
    public $unexpectedOutputSubstrings = [];

    /**
     * @var array
     */
    public $expectedTables = [];

    /**
     * @var array
     */
    public $expectedQuestions = [];

    /**
     * @var array
     */
    public $expectedChoices = [];

    /**
     * @var bool
     */
    protected static $consoleCommandsRegistered = false;

    /**
     * Daftarkan command bawaan CFConsole ke aplikasi konsol sekali per proses.
     *
     * @return void
     */
    public static function registerConsoleCommands() {
        if (static::$consoleCommandsRegistered) {
            return;
        }
        static::$consoleCommandsRegistered = true;
        CConsole_Application::starting(function (CConsole_Application $cfCli) {
            $cfCli->resolveCommands(array_merge(CFConsole::$defaultCommands, CFConsole::$commands));
        });
    }

    /**
     * @param string $command
     * @param array  $parameters
     *
     * @return CTesting_PendingCommand|int
     */
    public function cf($command, $parameters = []) {
        static::registerConsoleCommands();
        if (!$this->mockConsoleOutput) {
            return (new CConsole_Kernel())->call($command, $parameters);
        }

        return new CTesting_PendingCommand($this, $command, $parameters);
    }

    /**
     * @return $this
     */
    protected function withoutMockingConsoleOutput() {
        $this->mockConsoleOutput = false;

        return $this;
    }
}
