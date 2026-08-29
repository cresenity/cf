<?php

/**
 * Pulls CLAUDE.md down from devcloud's app_document store (see
 * DEVCLOUD-AI-TODO-Management-Platform-SPEC.md §18) via the `doc/fetch`
 * method, and writes it over the local file. This is the command developers
 * run to pick up whatever CLAUDE.md devcloud currently holds for their app -
 * devcloud is the source of truth here, not the local checkout (Hery,
 * 2026-08-30). The one-time initial backfill (pushing today's local content
 * up to devcloud for every app before anyone starts pulling) is a separate,
 * one-off action Hery runs directly against production - not exposed here,
 * and not something this command's --app/--dry-run flags are meant to serve.
 *
 * docs/TODO.md and docs/BUG.md are deliberately out of scope (Hery,
 * 2026-08-30) - they already have a separate, task-row-based sync design
 * (§8), not a raw-document one.
 */
class CConsole_Command_Claude_SyncCommand extends CConsole_Command {
    /**
     * @var string
     */
    const DOC_TYPE = 'CLAUDE_MD';

    /**
     * @var string
     */
    protected $signature = 'claude:sync
        {--app= : app_code to sync (defaults to auto-detect from the working directory, or "framework" at docroot)}
        {--dry-run : Show what would be written without touching the local file}';

    /**
     * @var string
     */
    protected $description = 'Pull CLAUDE.md down from devcloud and write it locally (doc/fetch)';

    /**
     * @return int
     */
    public function handle() {
        $appCode = $this->resolveAppCode();
        $target = $this->baseDirFor($appCode) . 'CLAUDE.md';

        $this->info("Fetching CLAUDE.md for '{$appCode}' from devcloud...");

        try {
            $data = CDevSuite::devCloudApi()->request('doc/fetch', [
                'appCode' => $appCode,
                'docType' => static::DOC_TYPE,
            ], 'post');
        } catch (Exception $e) {
            $this->error('Fetch failed: ' . $e->getMessage());

            return CConsole::FAILURE_EXIT;
        }

        $content = carr::get($data, 'content');
        $remoteHash = carr::get($data, 'contentHash');
        $updatedAt = carr::get($data, 'updatedAt');
        $updatedBy = carr::get($data, 'updatedBy');

        $localHash = CFile::exists($target) ? hash('sha256', CFile::get($target)) : null;

        if ($localHash === $remoteHash) {
            $this->info('Already up to date (' . $remoteHash . ').');

            return CConsole::SUCCESS_EXIT;
        }

        $this->line("  devcloud hash {$remoteHash}, last updated {$updatedAt} by {$updatedBy}");
        $this->line('  local hash ' . ($localHash ?: '(no local file)'));

        if ($this->option('dry-run')) {
            $this->line("  would write {$target} (dry-run, nothing changed)");

            return CConsole::SUCCESS_EXIT;
        }

        CFile::put($target, $content);
        $this->info("Wrote {$target}");

        return CConsole::SUCCESS_EXIT;
    }

    /**
     * --app wins; otherwise an application/<app>/ working directory resolves
     * to that app_code, and anything else (docroot itself included) resolves
     * to the framework sentinel, matching DModel_AppDocument::FRAMEWORK_APP_CODE.
     *
     * @return string
     */
    protected function resolveAppCode() {
        $explicit = $this->option('app');
        if (!empty($explicit)) {
            return $explicit;
        }

        $appsRoot = c::fixPath(DOCROOT . 'application');
        $cwd = c::fixPath(getcwd());

        if (cstr::startsWith($cwd, $appsRoot)) {
            $relative = trim(substr($cwd, strlen($appsRoot)), DS);
            if (strlen($relative) > 0) {
                $segments = explode(DS, $relative);

                return $segments[0];
            }
        }

        return 'framework';
    }

    /**
     * @param string $appCode
     *
     * @return string
     */
    protected function baseDirFor($appCode) {
        if ($appCode === 'framework') {
            return c::fixPath(DOCROOT);
        }

        return c::fixPath(DOCROOT . 'application' . DS . $appCode);
    }
}
