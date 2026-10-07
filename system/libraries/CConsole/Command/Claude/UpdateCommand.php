<?php

/**
 * Re-registers the remote DevCloud MCP server (claude:install --force).
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
    protected $description = 'Re-register the remote DevCloud MCP server (equivalent to claude:install --force)';

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
