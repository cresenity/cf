<?php
/**
 * PreToolUse hook (Write|Edit|NotebookEdit): blocks direct edits to files that
 * Cresenity Framework projects generate from devcloud, not by hand - CLAUDE.md,
 * docs/TODO.md, docs/BACKLOG.md, at any depth (root or application/<app>/).
 *
 * Installed into every developer's `~/.claude/settings.json` by
 * `phpcf claude:install` (see CDevSuite_ClaudeHookInstaller) - referenced from
 * this framework path directly rather than copied into the home directory, so
 * a `git pull` on the framework updates the hook logic for everyone without
 * needing to re-run the installer.
 *
 * Prompted by an actual incident (2026-08-30): the root CLAUDE.md itself
 * documents "never hand-edit, typo fixes included" for these three files, but
 * that is prose loaded into context - nothing stopped a session from using
 * Edit on CLAUDE.md anyway. This hook is the technical backstop.
 */

$input = json_decode(stream_get_contents(STDIN), true) ?: [];
$toolInput = $input['tool_input'] ?? [];
$path = $toolInput['file_path'] ?? $toolInput['notebook_path'] ?? '';

if ($path === '') {
    exit(0);
}

$base = basename($path);
$reason = null;

if ($base === 'CLAUDE.md') {
    $reason = 'CLAUDE.md is devcloud-managed (devcloud is source of truth) - never hand-edit, typo fixes included. '
        . 'Use claude_md_pull to read the current devcloud copy, then claude_md_push (appCode for the app, or "cf" for this root file) to write changes.';
} elseif (preg_match('#(^|/)docs/TODO\.md$#', $path)) {
    $reason = 'docs/TODO.md is rendered from devcloud project_task rows - never hand-edit. '
        . 'Change tasks via todo_create/todo_start/todo_cancel/todo_complete/todo_analyze, then re-render with todo_sync (todo_hash first to check if a sync is even needed).';
} elseif (preg_match('#(^|/)docs/BACKLOG\.md$#', $path)) {
    $reason = 'docs/BACKLOG.md is rendered from devcloud project_backlog rows - never hand-edit. '
        . 'Change entries via backlog_create/backlog_promote/backlog_reject, then re-render with backlog_sync (backlog_hash first to check if a sync is even needed).';
}

if ($reason === null) {
    exit(0);
}

echo json_encode([
    'hookSpecificOutput' => [
        'hookEventName' => 'PreToolUse',
        'permissionDecision' => 'deny',
        'permissionDecisionReason' => $reason,
    ],
]);
