<?php

class CFConsole {
    /**
     * The commands that are available to the console application.
     *
     * @var array
     */
    public static $commands = [];

    /**
     * The default commands that are always available.
     *
     * @var array
     */
    public static $defaultCommands = [
        CConsole_Command_VersionCommand::class,
        CConsole_Command_AboutCommand::class,
        CConsole_Command_ServeCommand::class,
        CConsole_Command_TinkerCommand::class,
        CConsole_Command_KeyGenerateCommand::class,
        CConsole_Command_ComposerCommand::class,
        CConsole_Command_EnvironmentCommand::class,
        CConsole_Command_Database_DbCommand::class,
        CConsole_Command_Database_ExplainCommand::class,
        CConsole_Command_Database_MonitorCommand::class,
        CConsole_Command_Database_ShowCommand::class,
        CConsole_Command_Database_SchemaCommand::class,
        CConsole_Command_Api_JWTSecretCommand::class,
        CConsole_Command_Api_OAuth_KeyCommand::class,
        CConsole_Command_Api_OAuth_ClientCommand::class,
        CConsole_Command_Domain_DomainListCommand::class,
        CConsole_Command_Domain_DomainCreateCommand::class,
        CConsole_Command_Domain_DomainDeleteCommand::class,
        CConsole_Command_Domain_DomainSwitchCommand::class,
        CConsole_Command_Daemon_DaemonListCommand::class,
        CConsole_Command_Daemon_DaemonStartCommand::class,
        CConsole_Command_Daemon_DaemonStatusCommand::class,
        CConsole_Command_Daemon_DaemonStopCommand::class,
        CConsole_Command_Daemon_Supervisor_StartCommand::class,
        CConsole_Command_Daemon_Supervisor_StatusCommand::class,
        CConsole_Command_Cron_ScheduleListCommand::class,
        CConsole_Command_Cron_ScheduleFinishCommand::class,
        CConsole_Command_Cron_ScheduleRunCommand::class,
        CConsole_Command_Cron_ScheduleWorkCommand::class,
        CConsole_Command_Cron_ScheduleTestCommand::class,
        CConsole_Command_Queue_ClearCommand::class,
        CConsole_Command_Queue_ListFailedCommand::class,
        CConsole_Command_Queue_PruneBatchesCommand::class,
        CConsole_Command_Translation_Check::class,
        CConsole_Command_DevSuite_DevSuiteInstallCommand::class,
        CConsole_Command_DevSuite_DevSuiteStartCommand::class,
        CConsole_Command_DevSuite_DevSuiteUninstallCommand::class,
        CConsole_Command_DevSuite_DevSuiteLinkCommand::class,
        CConsole_Command_DevSuite_DevSuiteLinksCommand::class,
        CConsole_Command_DevSuite_DevSuiteUnlinkCommand::class,
        CConsole_Command_DevSuite_DevSuiteSecureCommand::class,
        CConsole_Command_DevSuite_DevSuiteUnsecureCommand::class,
        CConsole_Command_DevSuite_DevSuiteTldCommand::class,
        CConsole_Command_DevSuite_DevSuiteOpenCommand::class,
        CConsole_Command_DevSuite_DevSuiteShareCommand::class,
        CConsole_Command_DevSuite_DevSuiteStartCommand::class,
        CConsole_Command_DevSuite_DevSuiteRestartCommand::class,
        CConsole_Command_DevSuite_DevSuiteStopCommand::class,
        CConsole_Command_DevSuite_DevSuiteDbInstallCommand::class,
        CConsole_Command_DevSuite_DevSuiteDbStartCommand::class,
        CConsole_Command_DevSuite_DevSuiteDbUninstallCommand::class,
        CConsole_Command_DevSuite_DevSuiteDbListCommand::class,
        CConsole_Command_DevSuite_DevSuiteDbCreateCommand::class,
        CConsole_Command_DevSuite_DevSuiteDbDeleteCommand::class,
        CConsole_Command_DevSuite_DevSuiteDbCompareCommand::class,
        CConsole_Command_DevSuite_DevSuiteDbCloneCommand::class,
        CConsole_Command_DevSuite_DevSuiteDbSyncCommand::class,
        CConsole_Command_DevSuite_DevSuiteSshCommand::class,
        CConsole_Command_DevSuite_DevSuiteSshListCommand::class,
        CConsole_Command_DevSuite_DevSuiteSshCreateCommand::class,
        CConsole_Command_DevCloud_DevCloudLoginCommand::class,
        CConsole_Command_DevCloud_DevCloudSessionCommand::class,
        CConsole_Command_DevCloud_DevCloudTeamsCommand::class,
        CConsole_Command_DevCloud_DevCloudTeamSwitchCommand::class,
        CConsole_Command_DevCloud_App_DevCloudAppInfoCommand::class,
        CConsole_Command_DevCloud_Project_DevCloudProjectCreateCommand::class,
        CConsole_Command_DevCloud_Git_DevCloudGitCreateCommand::class,
        CConsole_Command_DevCloud_App_DevCloudAppCreateCommand::class,
        CConsole_Command_DevCloud_Database_DevCloudDatabaseCreateDevCommand::class,
        CConsole_Command_DevCloud_App_DevCloudAppScaffoldCommand::class,
        CConsole_Command_DevCloud_Server_Command_DevCloudServerCommandListCommand::class,
        CConsole_Command_DevCloud_Server_Command_DevCloudServerCommandRunCommand::class,
        CConsole_Command_DevCloud_Server_DevCloudServerListCommand::class,
        CConsole_Command_DevCloud_Server_DevCloudServerStatusCommand::class,
        CConsole_Command_DevCloud_Server_DevCloudServerWebServerCommand::class,
        CConsole_Command_DevCloud_Server_Vhost_DevCloudServerVhostListCommand::class,
        CConsole_Command_DevCloud_Server_Service_DevCloudServerServiceControlCommand::class,
        CConsole_Command_DevCloud_Collector_DevCloudCollectorExceptionCommand::class,
        CConsole_Command_DevCloud_Collector_DevCloudCollectorExceptionListCommand::class,
        CConsole_Command_Make_MakeControllerCommand::class,
        CConsole_Command_Make_MakeModelCommand::class,
        CConsole_Command_Make_MakeConfigCommand::class,
        CConsole_Command_Make_MakeNavCommand::class,
        CConsole_Command_Make_MakeThemeCommand::class,
        CConsole_Command_Make_MakeTestCommand::class,
        CConsole_Command_Model_ModelListCommand::class,
        CConsole_Command_Model_ModelShowCommand::class,
        CConsole_Command_Model_ModelTablesCommand::class,
        CConsole_Command_Model_ModelUpdateCommand::class,
        CConsole_Command_Asset_GoogleFontsFetchCommand::class,
        CConsole_Command_App_AppInitCommand::class,
        CConsole_Command_Claude_InitCommand::class,
        CConsole_Command_Claude_InstallCommand::class,
        CConsole_Command_Claude_UpdateCommand::class,
        CConsole_Command_Claude_SyncCommand::class,
        CConsole_Command_App_AppCodeCommand::class,
        CConsole_Command_TestInstallCommand::class,
        CConsole_Command_TestCommand::class,
        CConsole_Command_NpmCommand::class,
        CConsole_Command_Phpstan_InstallCommand::class,
        CConsole_Command_PhpstanCommand::class,
        CConsole_Command_Phpstan_ClearCommand::class,
        CConsole_Command_Phpcs_InstallCommand::class,
        CConsole_Command_Phpcs_ConfigCommand::class,
        CConsole_Command_PhpcsCommand::class,
        CConsole_Command_Phpcs_FixCommand::class,
        CConsole_Command_Phpcsfixer_InstallCommand::class,
        CConsole_Command_Phpcsfixer_FormatCommand::class,
        CConsole_Command_Phpcsfixer_ConfigCommand::class,
        CConsole_Command_PhpcsfixerCommand::class,
        CWebSocket_Console_Command_StartServer::class,
        CTesting_Browser_Console_ChromeDriverCommand::class,
        CTesting_Browser_Console_BrowserCommand::class,
        CTesting_Browser_Console_BrowserFailsCommand::class,
        CTesting_Browser_Console_PurgeCommand::class,
        CTesting_Browser_Console_InstallCommand::class,
        CTesting_Browser_Console_MakeCommand::class,
        CTesting_Browser_Console_PageCommand::class,
        CTesting_Browser_Console_ComponentCommand::class,
        CConsole_Command_Server_Monitor_ListenCommand::class,
        CConsole_Command_Docs_PhpDoc_InstallCommand::class,
        CConsole_Command_Docs_PhpDoc_GenerateCommand::class,
        CConsole_Command_Docs_ApiGen_InstallCommand::class,
        CConsole_Command_Docs_ApiGen_GenerateCommand::class,
        CConsole_Command_CF_TestCommand::class,

    ];

    /**
     * Execute the console application.
     *
     * @return void
     */
    public static function execute() {
        $kernel = new CConsole_Kernel();
        $commands = array_merge(static::$defaultCommands, static::$commands);
        CConsole_Application::starting(function ($cfCli) use ($commands) {
            $cfCli->resolveCommands($commands);
        });

        $status = $kernel->handle(
            $input = new Symfony\Component\Console\Input\ArgvInput(),
            new Symfony\Component\Console\Output\ConsoleOutput()
        );

        $kernel->terminate($input, $status);

        exit($status);
    }

    /**
     * Add a command class to the console application.
     *
     * @param string|array $classArray
     *
     * @return void
     */
    public static function addCommand($classArray) {
        $classArray = carr::wrap($classArray);
        foreach ($classArray as $class) {
            if (!class_exists($class)) {
                throw new Exception('Class ' . $class . ' not exists');
            }

            static::$commands[] = $class;
        }
    }
}
