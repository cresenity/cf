<?php

use Symfony\Component\Process\Process;

/**
 * Installs devcloud-mcp into Claude Code as a Claude Code Plugin (changed
 * 2026-08-30, was a bare `claude mcp add`/npx registration before). The
 * plugin's code lives in `cresenity/devcloud-mcp` (private repo); this repo's
 * own `cresenity/devcloud-claude-marketplace` is a thin index pointing at it,
 * not a copy - see that repo's README.
 *
 * Node resolution (>=18, since the system-default `node` is often much older
 * or entirely missing from PATH outside an interactive shell) and the build
 * step both moved into the plugin itself - `bin/run.sh` resolves Node at
 * runtime, and `dist/bundle.cjs` is a committed, self-contained esbuild
 * bundle (a plain `github`/`git` plugin source only does a raw `git clone`,
 * no `npm install`/build step at all). Nothing here needs to warm up a cache
 * or find Node anymore - that whole category of problem (measured directly:
 * npx silently reusing a stale cached build, a bare `node` command resolving
 * an incompatible ancient version) lives inside the plugin now, not here.
 *
 * Always does a full uninstall+reinstall rather than trusting
 * `claude plugin update`'s own version-diffing - that command compares
 * `plugin.json`'s declared version string, which would need a manual bump on
 * every push to be noticed. A fresh install always re-clones current HEAD
 * regardless of the version field, so this sidesteps that entirely (same
 * "never trust an implicit staleness check" lesson as the old npx cache).
 *
 * Also runs `claude:sync` at the end - installing the plugin without a local
 * CLAUDE.md that matches devcloud's copy would leave a session working off
 * stale instructions from the very first prompt.
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
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'claude:install
        {--scope=user : Claude Code plugin scope: user, project, or local}
        {--force : Uninstall an existing installation of this plugin before installing}';

    /**
     * @var string
     */
    protected $description = 'Install the devcloud-mcp Claude Code Plugin (claude plugin install)';

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

        $pluginRef = static::PLUGIN_NAME . '@' . static::MARKETPLACE_NAME;

        $this->info('Adding marketplace ' . static::MARKETPLACE . '...');
        $this->runClaude($claudeBinary, ['plugin', 'marketplace', 'add', static::MARKETPLACE]);

        $this->info('Refreshing marketplace (forces a fresh pull, not a cached one)...');
        $this->runClaude($claudeBinary, ['plugin', 'marketplace', 'update', static::MARKETPLACE_NAME]);

        if ($this->option('force')) {
            $this->line("Removing any existing '{$pluginRef}' installation ({$scope} scope)...");
            $this->runClaude($claudeBinary, ['plugin', 'uninstall', $pluginRef, '--scope', $scope]);
        }

        $this->info("Installing {$pluginRef} ({$scope} scope)...");
        $installProcess = $this->runClaude($claudeBinary, ['plugin', 'install', $pluginRef, '--scope', $scope]);

        if (!$installProcess->isSuccessful()) {
            $this->error('`claude plugin install` failed. Pass --force to reinstall over an existing one.');

            return CConsole::FAILURE_EXIT;
        }

        $this->info("devcloud-mcp installed ({$scope} scope).");
        $this->line('Restart any running Claude Code session (or run `/mcp` reconnect) for it to pick up the new version.');

        $this->call('claude:sync');

        return CConsole::SUCCESS_EXIT;
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
     *
     * @return Process
     */
    protected function runClaude($claudeBinary, array $args) {
        $process = new Process(array_merge([$claudeBinary], $args));
        $process->setTimeout(180);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        return $process;
    }

    /**
     * Reminder only - devcloud-mcp itself already fails each tool call with a
     * clear message when the token is missing/expired, so this does not block
     * installation.
     *
     * @return void
     */
    protected function warnIfNotLoggedIn() {
        $tokenPath = CDevSuite::homePath() . 'devcloud' . DS . 'oauth.json';
        if (!CFile::exists($tokenPath)) {
            $this->warn('Not logged in to devcloud yet on this machine.');
            $this->line('Run `phpcf devcloud:login` before using devcloud-mcp tools in Claude Code.');
        }
    }
}
