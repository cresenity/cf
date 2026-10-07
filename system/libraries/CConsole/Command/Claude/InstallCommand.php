<?php

use Symfony\Component\Process\Process;

/**
 * Registers the remote DevCloud MCP server (`https://devcloud.cresenity.com/mcp`) in Claude Code
 * and removes the legacy Node plugin `devcloud-mcp` when it is present. The remote server needs
 * one interactive login afterwards (`claude mcp login devcloud`), which cannot run from here.
 *
 * Also runs `claude:sync` and installs the doc-guard hook, as before.
 */
class CConsole_Command_Claude_InstallCommand extends CConsole_Command {
    /**
     * @var string
     */
    const MARKETPLACE = 'cresenity/devcloud-claude-marketplace';

    /**
     * @var string
     */
    const MARKETPLACE_NAME = 'devcloud-claude-marketplace';

    /**
     * @var string
     */
    const PLUGIN_NAME = 'devcloud-mcp';

    /**
     * @var string
     */
    const MCP_SERVER_NAME = 'devcloud';

    /**
     * @var string
     */
    const MCP_SERVER_URL = 'https://devcloud.cresenity.com/mcp';

    /**
     * @var string
     */
    const MCP_CLIENT_ID = '29';

    /**
     * @var string
     */
    const MCP_CALLBACK_PORT = '8765';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'claude:install
        {--scope=user : Claude Code plugin scope: user, project, or local}
        {--force : Re-register the remote MCP server even when it is already registered}
        {--keep-legacy : Do not uninstall the legacy devcloud-mcp Node plugin}';

    /**
     * @var string
     */
    protected $description = 'Register the remote DevCloud MCP server in Claude Code and remove the legacy devcloud-mcp plugin';

    /**
     * @return int
     */
    public function handle() {
        $claudeBinary = $this->findClaudeBinary();
        if ($claudeBinary == null) {
            $this->error('`claude` command not found in PATH.');
            $this->line('Install Claude Code on this machine first, then re-run `phpcf claude:install`.');

            return CConsole::FAILURE_EXIT;
        }

        $scope = (string) $this->option('scope');
        if (!in_array($scope, ['user', 'project', 'local'], true)) {
            $this->error("Invalid --scope '{$scope}'. Use user, project, or local.");

            return CConsole::FAILURE_EXIT;
        }

        $this->warnIfNotLoggedIn();

        if (!$this->option('keep-legacy')) {
            $this->removeLegacyPlugin($claudeBinary, $scope);
        }

        if (!$this->registerRemoteServer($claudeBinary, $scope)) {
            return CConsole::FAILURE_EXIT;
        }

        $this->info('Remote MCP server registered (' . $scope . ' scope).');
        $this->line('Next: run `claude mcp login ' . static::MCP_SERVER_NAME . '` in a normal terminal (one-time browser login), then start a new Claude Code session.');

        $this->call('claude:sync');

        $this->installDocGuardHook();

        return CConsole::SUCCESS_EXIT;
    }

    /**
     * Uninstalls the legacy Node plugin when installed; absence is not an error.
     *
     * @param string $claudeBinary
     * @param string $scope
     *
     * @return void
     */
    protected function removeLegacyPlugin($claudeBinary, $scope) {
        $pluginRef = static::PLUGIN_NAME . '@' . static::MARKETPLACE_NAME;
        $list = $this->runClaude($claudeBinary, ['plugin', 'list'], false);
        if (!$list->isSuccessful() || strpos($list->getOutput(), static::PLUGIN_NAME) === false) {
            $this->line('Legacy plugin ' . static::PLUGIN_NAME . ' not installed.');

            return;
        }

        $this->info("Removing legacy plugin '{$pluginRef}' ({$scope} scope)...");
        $uninstall = $this->runClaude($claudeBinary, ['plugin', 'uninstall', $pluginRef, '--scope', $scope]);
        if (!$uninstall->isSuccessful()) {
            $this->warn('Could not uninstall the legacy plugin; run `claude plugin uninstall ' . $pluginRef . '` manually (add --scope if it was installed elsewhere).');
        }
    }

    /**
     * Adds the remote MCP server unless it is already registered (or --force replaces it).
     *
     * @param string $claudeBinary
     * @param string $scope
     *
     * @return bool
     */
    protected function registerRemoteServer($claudeBinary, $scope) {
        $existing = $this->runClaude($claudeBinary, ['mcp', 'get', static::MCP_SERVER_NAME], false);
        if ($existing->isSuccessful()) {
            if (strpos($existing->getOutput(), static::MCP_SERVER_URL) !== false && !$this->option('force')) {
                $this->line('Remote MCP server already registered.');

                return true;
            }

            $this->line('Replacing existing MCP server entry...');
            $this->runClaude($claudeBinary, ['mcp', 'remove', static::MCP_SERVER_NAME, '--scope', $scope]);
        }

        $this->info('Registering ' . static::MCP_SERVER_URL . '...');
        $add = $this->runClaude($claudeBinary, [
            'mcp', 'add', '--transport', 'http', '--scope', $scope,
            '--client-id', static::MCP_CLIENT_ID, '--callback-port', static::MCP_CALLBACK_PORT,
            static::MCP_SERVER_NAME, static::MCP_SERVER_URL,
        ]);
        if (!$add->isSuccessful()) {
            $this->error('`claude mcp add` failed.');

            return false;
        }

        return true;
    }

    /**
     * Installs the PreToolUse hook that blocks hand-edits of CLAUDE.md,
     * docs/TODO.md and docs/BACKLOG.md (see CDevSuite_ClaudeHookInstaller).
     * Not fatal on failure - devcloud-mcp itself still works without it, this
     * is a local safety net on top.
     *
     * @return void
     */
    protected function installDocGuardHook() {
        try {
            $result = CDevSuite_ClaudeHookInstaller::install();
        } catch (Exception $e) {
            $this->warn('Could not install the devcloud-doc-guard hook: ' . $e->getMessage());

            return;
        }

        $action = carr::get($result, 'action');
        $settingsPath = carr::get($result, 'settingsPath');

        if ($action === 'unchanged') {
            $this->line("devcloud-doc-guard hook already up to date in {$settingsPath}.");

            return;
        }

        $verb = $action === 'updated' ? 'Updated' : 'Installed';
        $this->info("{$verb} devcloud-doc-guard hook in {$settingsPath} (blocks hand-edits of CLAUDE.md/docs/TODO.md/docs/BACKLOG.md).");
        $this->line('Restart any running Claude Code session (or run `/hooks`) for it to pick up the change.');
    }

    /**
     * @return null|string
     */
    protected function findClaudeBinary() {
        $finder = new Symfony\Component\Process\ExecutableFinder();

        return $finder->find('claude');
    }

    /**
     * @param string $claudeBinary
     * @param array  $args
     * @param bool   $echo   whether to print the command output
     *
     * @return Process
     */
    protected function runClaude($claudeBinary, array $args, $echo = true) {
        $process = new Process(array_merge([$claudeBinary], $args));
        $process->setTimeout(180);
        $process->run(function ($type, $buffer) use ($echo) {
            if ($echo) {
                $this->output->write($buffer);
            }
        });

        return $process;
    }

    /**
     * Reminder only: `phpcf devcloud:login` is still what the other phpcf devcloud
     * commands use (the MCP server has its own `claude mcp login`).
     *
     * @return void
     */
    protected function warnIfNotLoggedIn() {
        $tokenPath = CDevSuite::homePath() . 'devcloud' . DS . 'oauth.json';
        if (!CFile::exists($tokenPath)) {
            $this->warn('Not logged in to devcloud yet on this machine.');
            $this->line('Run `phpcf devcloud:login` before using the other phpcf devcloud commands.');
        }
    }
}
