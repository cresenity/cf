<?php

use Symfony\Component\Process\Process;

/**
 * Registers the devcloud-mcp server into Claude Code, so its TODO/task tools
 * become available in any Claude Code session without hand-editing MCP config.
 *
 * Always runs the canonical build from github.com/cresenity/devcloud-mcp via
 * npx - deliberately not a locally built/editable checkout. That single
 * source of truth is what makes `claude:update` meaningful (every developer
 * gets the same code).
 *
 * Uses an explicit `git+ssh://` spec rather than the `github:owner/repo`
 * shorthand (changed 2026-08-30) - the shorthand's protocol resolution isn't
 * reliable across environments: it worked over plain SSH in one shell but a
 * VSCode-spawned reconnect hit an HTTPS username/password prompt for the same
 * private repo, on the same machine, same registration. Forcing git+ssh here
 * removes the ambiguity - it only ever needs the SSH key that's already used
 * for every other git operation in this repo, never HTTPS credentials.
 *
 * "Auto-update for free" from an unpinned spec is **not guaranteed** - measured
 * 2026-08-30: npx can silently reuse a stale cached build instead of
 * re-resolving against the remote's current HEAD (a cache from an earlier
 * commit was reused as-is, missing everything pushed since). `bustNpxCache()`
 * below exists because of this - it removes any cached copy before every
 * warm-up/registration, so a stale build is never silently trusted again.
 */
class CConsole_Command_Claude_InstallCommand extends CConsole_Command {
    /**
     * @var string
     */
    const GITHUB_SPEC = 'git+ssh://git@github.com/cresenity/devcloud-mcp.git';

    /**
     * devcloud-mcp needs Node >= 18 (see its README) - the system-default
     * `node` on a dev machine is often much older (nvm installs live outside
     * PATH unless a shell has `nvm use` applied), so this cannot be assumed.
     *
     * @var int
     */
    const MIN_NODE_MAJOR = 18;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'claude:install
        {--scope=user : Claude Code MCP scope: user, project, or local}
        {--name=devcloud : Name to register the MCP server under}
        {--force : Remove an existing registration under this name before adding}';

    /**
     * @var string
     */
    protected $description = 'Register the devcloud-mcp server into Claude Code (claude mcp add)';

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

        $name = (string) $this->option('name');
        $scope = (string) $this->option('scope');
        if (!in_array($scope, ['user', 'project', 'local'], true)) {
            $this->error("Invalid --scope '{$scope}'. Use user, project, or local.");

            return CConsole::FAILURE_EXIT;
        }

        $nodeBinDir = $this->findNodeBinDir();
        if ($nodeBinDir == null) {
            $this->error('No Node.js >= ' . static::MIN_NODE_MAJOR . ' found (checked PATH and ~/.nvm/versions/node).');
            $this->line('Install one (e.g. `nvm install ' . static::MIN_NODE_MAJOR . '`) and re-run `phpcf claude:install`.');

            return CConsole::FAILURE_EXIT;
        }

        if ($this->option('force')) {
            $this->line("Removing any existing '{$name}' registration ({$scope} scope)...");
            Process::fromShellCommandline(
                $this->buildCommandLine([$claudeBinary, 'mcp', 'remove', $name, '-s', $scope])
            )->run();
        }

        $this->warnIfNotLoggedIn();

        // -e PATH=... makes sure npx's own re-exec of `node` (via its shebang)
        // resolves the same Node >= 18, regardless of whatever PATH Claude
        // Code itself was started with.
        $pathEnv = $nodeBinDir . PATH_SEPARATOR . getenv('PATH');
        $runCommand = [$nodeBinDir . DS . 'npx', '-y', static::GITHUB_SPEC];

        $this->warmUpNpxCache($runCommand);

        $this->info('Registering devcloud-mcp with Claude Code (' . static::GITHUB_SPEC . ' via npx)...');
        $addProcess = new Process(array_merge(
            [$claudeBinary, 'mcp', 'add', $name, '-s', $scope, '-e', 'PATH=' . $pathEnv, '--'],
            $runCommand
        ));
        $addProcess->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (!$addProcess->isSuccessful()) {
            $this->error('`claude mcp add` failed. Pass --force to replace an existing registration under the same name.');

            return CConsole::FAILURE_EXIT;
        }

        $this->info("devcloud-mcp registered as '{$name}' ({$scope} scope).");
        $this->line('Restart any running Claude Code session (or start a new one) for it to pick up the new server.');

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
     * Resolves an absolute directory containing a `node`/`npx` >= the minimum
     * version - checks PATH first, then falls back to scanning nvm's
     * installed versions, since nvm-managed Node often is not on PATH outside
     * an interactive shell that ran `nvm use`.
     *
     * @return null|string
     */
    protected function findNodeBinDir() {
        $finder = new Symfony\Component\Process\ExecutableFinder();
        $pathNode = $finder->find('node');
        if ($pathNode != null && $this->nodeMajorVersion($pathNode) >= static::MIN_NODE_MAJOR) {
            return dirname($pathNode);
        }

        $nvmRoot = getenv('HOME') . DS . '.nvm' . DS . 'versions' . DS . 'node';
        if (!CFile::isDirectory($nvmRoot)) {
            return null;
        }

        $best = null;
        foreach (glob($nvmRoot . DS . 'v*') as $versionDir) {
            $version = ltrim(basename($versionDir), 'v');
            if ((int) $version < static::MIN_NODE_MAJOR) {
                continue;
            }
            if ($best === null || version_compare($version, $best, '>')) {
                $best = $version;
            }
        }

        return $best !== null ? $nvmRoot . DS . 'v' . $best . DS . 'bin' : null;
    }

    /**
     * @param string $nodeBinary
     *
     * @return int
     */
    protected function nodeMajorVersion($nodeBinary) {
        $process = new Process([$nodeBinary, '--version']);
        $process->run();
        if (!$process->isSuccessful()) {
            return 0;
        }

        return (int) ltrim(trim($process->getOutput()), 'v');
    }

    /**
     * A cold `npx github:...` run (clone + npm install + our `prepare` tsc
     * build) measured 27-36s - right at or past Claude Code's own ~30s MCP
     * connectivity-check timeout, so the very first `claude mcp list` after
     * install would show "Failed to connect" even though the server is fine
     * (a warm run measured ~8s). Running it once here, before registering,
     * populates npx's cache so the real connection Claude Code makes next is
     * fast. Failure here is not fatal - just means the first real connection
     * attempt pays the cold-start cost instead.
     *
     * @param array $runCommand
     *
     * @return void
     */
    protected function warmUpNpxCache(array $runCommand) {
        $this->bustNpxCache();

        $this->line('Warming up devcloud-mcp (first install downloads + builds it, up to ~1 minute)...');

        $process = new Process($runCommand);
        $process->setInput('');
        $process->setTimeout(180);

        try {
            $process->run();
        } catch (Exception $e) {
            $this->warn('Warm-up did not finish cleanly (' . $e->getMessage() . ') - continuing anyway.');
        }
    }

    /**
     * Removes any cached npx install of devcloud-mcp, so the next `npx` run is
     * forced to fetch+build fresh rather than possibly reusing a stale cached
     * copy silently (see this class's own docblock - confirmed 2026-08-30 that
     * npx does not reliably re-resolve an unpinned spec on its own).
     *
     * @return void
     */
    protected function bustNpxCache() {
        $npxRoot = getenv('HOME') . DS . '.npm' . DS . '_npx';
        if (!CFile::isDirectory($npxRoot)) {
            return;
        }

        foreach (glob($npxRoot . DS . '*') as $entry) {
            if (CFile::isDirectory($entry . DS . 'node_modules' . DS . 'devcloud-mcp')) {
                CFile::deleteDirectory($entry);
            }
        }
    }

    /**
     * Reminder only - devcloud-mcp itself already fails each tool call with a
     * clear message when the token is missing/expired, so this does not block
     * registration.
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

    /**
     * @param array $parts
     *
     * @return string
     */
    protected function buildCommandLine(array $parts) {
        return implode(' ', array_map('escapeshellarg', $parts));
    }
}
