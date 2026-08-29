<?php

/**
 * Re-registers devcloud-mcp with Claude Code. In practice this rarely does
 * anything by itself - `claude:install` always points at the unpinned
 * `github:cresenity/devcloud-mcp` npx spec, which already re-resolves to
 * whatever is current on GitHub every time Claude Code starts the server.
 * This command exists for the cases that do need a fresh registration: the
 * scope/name changed, or the entry in Claude Code's config looks stuck.
 */
class CConsole_Command_Claude_UpdateCommand extends CConsole_Command {
    /**
     * @var string
     */
    protected $signature = 'claude:update
        {--scope=user : Claude Code MCP scope: user, project, or local}
        {--name=devcloud : Name the MCP server is registered under}';

    /**
     * @var string
     */
    protected $description = 'Re-register devcloud-mcp with Claude Code (equivalent to claude:install --force)';

    /**
     * @return int
     */
    public function handle() {
        return $this->call('claude:install', [
            '--scope' => $this->option('scope'),
            '--name' => $this->option('name'),
            '--force' => true,
        ]);
    }
}
