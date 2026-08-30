<?php

/**
 * Reinstalls the devcloud-mcp plugin (§ InstallCommand's own docblock - always a full
 * uninstall+reinstall, not `claude plugin update`, since that command's version-diffing needs a
 * manual bump on every push to notice anything changed). Run this any time devcloud-mcp changed
 * and you want it picked up now, rather than waiting for the next `/mcp`/plugin reconnect.
 */
class CConsole_Command_Claude_UpdateCommand extends CConsole_Command {
    /**
     * @var string
     */
    protected $signature = 'claude:update
        {--scope=user : Claude Code plugin scope: user, project, or local}';

    /**
     * @var string
     */
    protected $description = 'Reinstall the devcloud-mcp plugin (equivalent to claude:install --force)';

    /**
     * @return int
     */
    public function handle() {
        return $this->call('claude:install', [
            '--scope' => $this->option('scope'),
            '--force' => true,
        ]);
    }
}
