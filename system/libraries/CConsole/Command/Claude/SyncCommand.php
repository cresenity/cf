<?php

/**
 * Pulls CLAUDE.md and docs/TODO.md down from devcloud's per-app document store (see
 * DEVCLOUD-AI-TODO-Management-Platform-SPEC.md §18/§20) - devcloud is the source of
 * truth for both, not the local checkout (decided 2026-08-30).
 *
 * Default mode (no --app) scans every app folder under application/, project/,
 * frontend/, and mobile/ (plus 'cf' for the framework's own root CLAUDE.md/docs), hashes
 * whatever exists locally, and sends the whole list to devcloud's doc/hashBatch in one
 * request - added 2026-08-31 so a full-repo sync costs one round trip instead of one per
 * app per doc type. Anything devcloud reports as missing/different (including apps with
 * no local file at all) is pulled immediately in the same run.
 *
 * `--app=<code>` restricts this to a single app_code, matching the pre-2026-08-31
 * single-app behavior - still useful when only one app's docs are of interest.
 *
 * docs/BUG.md is deliberately out of scope here, same as before - it already has its
 * own task-row-based sync design (backlog_hash/backlog_sync), not a raw-document one.
 */
class CConsole_Command_Claude_SyncCommand extends CConsole_Command {
    /**
     * @var string
     */
    const DOC_CLAUDE_MD = 'CLAUDE_MD';

    /**
     * @var string
     */
    const DOC_TODO_MD = 'TODO_MD';

    /**
     * App-type folders scanned in default (all-apps) mode. Order only affects the
     * report's ordering, not behavior.
     *
     * @var array
     */
    const APP_ROOTS = ['application', 'project', 'frontend', 'mobile'];

    /**
     * @var string
     */
    protected $signature = 'claude:sync
        {--app= : Restrict to a single app_code instead of scanning every app folder}
        {--dry-run : Show what would be written without touching local files}';

    /**
     * @var string
     */
    protected $description = 'Pull CLAUDE.md and docs/TODO.md down from devcloud for every app (or one with --app)';

    /**
     * @return int
     */
    public function handle() {
        if (!empty($this->option('app'))) {
            return $this->syncOne($this->option('app'));
        }

        return $this->syncAll();
    }

    /**
     * Original single-app path (`--app=<code>`), unchanged in behavior from before
     * 2026-08-31 apart from now also covering docs/TODO.md, not just CLAUDE.md.
     *
     * @param string $appCode
     *
     * @return int
     */
    protected function syncOne($appCode) {
        $targets = $this->targetsFor($appCode);

        $this->info("Checking '{$appCode}' against devcloud...");

        return $this->syncMany([$appCode => $targets]);
    }

    /**
     * @return int
     */
    protected function syncAll() {
        $targets = ['cf' => $this->targetsFor('cf')];

        foreach (static::APP_ROOTS as $root) {
            $rootPath = c::fixPath(DOCROOT . $root);
            if (!CFile::isDirectory($rootPath)) {
                continue;
            }

            foreach (CFile::directories($rootPath) as $appDir) {
                $appCode = basename($appDir);
                if (cstr::startsWith($appCode, '.')) {
                    continue;
                }

                $targets[$appCode] = $this->targetsFor($appCode);
            }
        }

        $this->info('Checking ' . count($targets) . ' app(s) against devcloud...');

        return $this->syncMany($targets);
    }

    /**
     * Builds the local-file targets (path + current hash, null if the file doesn't
     * exist yet) for one app_code, for both doc types.
     *
     * @param string $appCode
     *
     * @return array{CLAUDE_MD: array{path: string, hash: null|string}, TODO_MD: array{path: string, hash: null|string}}
     */
    protected function targetsFor($appCode) {
        $base = $this->baseDirFor($appCode);

        $claudePath = $base . 'CLAUDE.md';
        $todoPath = $base . 'docs' . DS . 'TODO.md';

        return [
            static::DOC_CLAUDE_MD => ['path' => $claudePath, 'hash' => $this->hashIfExists($claudePath)],
            static::DOC_TODO_MD => ['path' => $todoPath, 'hash' => $this->hashIfExists($todoPath)],
        ];
    }

    /**
     * @param array<string, array<string, array{path: string, hash: null|string}>> $targets keyed by app_code
     *
     * @return int
     */
    protected function syncMany(array $targets) {
        $items = [];
        foreach ($targets as $appCode => $docs) {
            foreach ($docs as $docType => $unused) {
                $items[] = ['appCode' => $appCode, 'docType' => $docType];
            }
        }

        try {
            $data = CDevSuite::devCloudApi()->request('doc/hashBatch', ['items' => $items], 'post');
        } catch (Exception $e) {
            $this->error('Batch hash check failed: ' . $e->getMessage());

            return CConsole::FAILURE_EXIT;
        }

        $upToDate = 0;
        $pulled = 0;
        $skipped = 0;

        foreach (carr::get($data, 'results', []) as $result) {
            $appCode = carr::get($result, 'appCode');
            $docType = carr::get($result, 'docType');
            $target = carr::get($targets, $appCode . '.' . $docType);

            if ($target == null) {
                continue;
            }

            $errorCode = carr::get($result, 'errorCode');
            if ($errorCode != null) {
                // APP_NOT_FOUND/DOC_NOT_FOUND are expected for an app_code devcloud
                // doesn't know yet, or a doc type it has no content for - not a
                // failure, just nothing to pull.
                if ($errorCode !== 'APP_NOT_FOUND' && $errorCode !== 'DOC_NOT_FOUND') {
                    $this->line("  {$appCode}/{$docType}: " . carr::get($result, 'error'));
                }
                $skipped++;

                continue;
            }

            $remoteHash = carr::get($result, 'contentHash');
            if ($remoteHash === carr::get($target, 'hash')) {
                $upToDate++;

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("  would pull {$appCode}/{$docType} -> " . carr::get($target, 'path'));
                $pulled++;

                continue;
            }

            if ($this->pullOne($appCode, $docType, carr::get($target, 'path'))) {
                $this->line("  pulled {$appCode}/{$docType}");
                $pulled++;
            } else {
                $skipped++;
            }
        }

        $suffix = $this->option('dry-run') ? ' (dry-run, nothing written)' : '';
        $this->info("Done: {$upToDate} up to date, {$pulled} pulled, {$skipped} skipped{$suffix}.");

        return CConsole::SUCCESS_EXIT;
    }

    /**
     * Fetches and writes the full content for one (appCode, docType), creating the
     * parent directory first since docs/TODO.md's folder may not exist locally yet.
     *
     * @param string $appCode
     * @param string $docType
     * @param string $target
     *
     * @return bool
     */
    protected function pullOne($appCode, $docType, $target) {
        try {
            if ($docType === static::DOC_TODO_MD) {
                $data = CDevSuite::devCloudApi()->request('todo/render', ['appCode' => $appCode], 'post');
                $content = carr::get($data, 'content');
            } else {
                $data = CDevSuite::devCloudApi()->request('doc/fetch', ['appCode' => $appCode, 'docType' => static::DOC_CLAUDE_MD], 'post');
                $content = carr::get($data, 'content');
            }
        } catch (Exception $e) {
            $this->error("  {$appCode}/{$docType}: fetch failed - " . $e->getMessage());

            return false;
        }

        CFile::ensureDirectoryExists(dirname($target));
        CFile::put($target, $content);

        return true;
    }

    /**
     * @param string $path
     *
     * @return null|string
     */
    protected function hashIfExists($path) {
        return CFile::exists($path) ? hash('sha256', CFile::get($path)) : null;
    }

    /**
     * @param string $appCode
     *
     * @return string
     */
    protected function baseDirFor($appCode) {
        if ($appCode === 'cf') {
            return c::fixPath(DOCROOT);
        }

        foreach (static::APP_ROOTS as $root) {
            $candidate = c::fixPath(DOCROOT . $root . DS . $appCode);
            if (CFile::isDirectory($candidate)) {
                return $candidate;
            }
        }

        // Not found locally yet (e.g. --app for a brand new app_code) - default to
        // application/<code>/, same fallback the pre-2026-08-31 command used.
        return c::fixPath(DOCROOT . 'application' . DS . $appCode);
    }
}
