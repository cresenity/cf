<?php

/**
 * Description of DevCloudSessionCommand
 */
class CConsole_Command_DevCloud_DevCloudSessionCommand extends CConsole_Command_DevSuiteCommand {
    /**
     * The class name of the devsuite command.
     *
     * @var string
     */
    protected $devSuiteCommandClass = CDevSuite_Command_DevCloud_SessionCommand::class;

    /**
     * @var string
     */
    protected $signature = 'devcloud:session';
}
