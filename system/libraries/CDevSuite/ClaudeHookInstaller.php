<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * Installs the devcloud-doc-guard PreToolUse hook into the developer's own
 * `~/.claude/settings.json` (user scope - applies to every Cresenity
 * Framework project, not just the one being installed from).
 *
 * The hook itself (`system/data/claude/hooks/block-devcloud-managed-files.php`)
 * stays inside the framework checkout and is referenced by absolute path
 * rather than copied into the home directory, so a later `git pull` on the
 * framework updates the hook logic for every developer without anyone
 * needing to re-run `phpcf claude:install`.
 */
class CDevSuite_ClaudeHookInstaller {
    /**
     * Substring that identifies our hook's command line among any others a
     * developer may have configured, regardless of which checkout path it
     * was last installed from.
     *
     * @var string
     */
    const MARKER = 'block-devcloud-managed-files.php';

    /**
     * @return array{action: string, settingsPath: string, hookPath: string}
     */
    public static function install() {
        $hookPath = c::fixPath(DOCROOT) . 'system' . DS . 'data' . DS . 'claude' . DS . 'hooks' . DS . static::MARKER;
        $settingsPath = static::settingsPath();

        $settings = CFile::exists($settingsPath) ? (json_decode(CFile::get($settingsPath), true) ?: []) : [];

        // Write is deliberately excluded: the sanctioned resync workflow
        // (todo_sync/backlog_sync/claude_md_pull) always overwrites the whole
        // file via Write ("timpa semuanya" per CLAUDE.md), so blocking Write
        // here blocks that legitimate flow too - only Edit/NotebookEdit are
        // the surgical, hand-edit-shaped calls this hook exists to catch.
        // Confirmed broken production impact on 2026-08-30, same day this
        // hook was introduced - see git log for this file.
        $entry = [
            'matcher' => 'Edit|NotebookEdit',
            'hooks' => [
                [
                    'type' => 'command',
                    'command' => 'php ' . $hookPath,
                    'statusMessage' => 'Checking if this file is devcloud-managed...',
                ],
            ],
        ];

        $preToolUse = carr::get($settings, 'hooks.PreToolUse', []);

        $existingIndex = null;
        foreach ($preToolUse as $index => $group) {
            foreach (carr::get($group, 'hooks', []) as $hook) {
                if (cstr::contains((string) carr::get($hook, 'command'), static::MARKER)) {
                    $existingIndex = $index;

                    break 2;
                }
            }
        }

        $action = 'added';
        if ($existingIndex !== null) {
            if ($preToolUse[$existingIndex] === $entry) {
                return ['action' => 'unchanged', 'settingsPath' => $settingsPath, 'hookPath' => $hookPath];
            }
            $preToolUse[$existingIndex] = $entry;
            $action = 'updated';
        } else {
            $preToolUse[] = $entry;
        }

        // carr::set() mutates $settings by reference; its return value is only the
        // innermost segment ($settings['hooks']), not the whole array, so it must
        // not be captured back into $settings here.
        carr::set($settings, 'hooks.PreToolUse', $preToolUse);

        CFile::makeDirectory(dirname($settingsPath), 0755, true, true);
        CFile::put($settingsPath, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

        return ['action' => $action, 'settingsPath' => $settingsPath, 'hookPath' => $hookPath];
    }

    /**
     * @return string
     */
    protected static function settingsPath() {
        $home = carr::get($_SERVER, 'HOME', '');

        return c::fixPath($home) . '.claude' . DS . 'settings.json';
    }
}
