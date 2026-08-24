<?php

/**
 * Scaffolds a CLAUDE.md for the application in the current working directory.
 */
class CConsole_Command_Claude_InitCommand extends CConsole_Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'claude:init {--force : Overwrite an existing CLAUDE.md}';

    /**
     * @var string
     */
    protected $description = 'Create a CLAUDE.md preloaded with CF conventions for this application';

    /**
     * @return int
     */
    public function handle() {
        $appCode = $this->resolveAppCode();
        if ($appCode == null) {
            $this->error('claude:init must be run from inside an application directory, e.g. application/ohayomart/');

            return CConsole::FAILURE_EXIT;
        }

        $target = c::fixPath(DOCROOT . 'application' . DS . $appCode) . 'CLAUDE.md';

        if (CFile::exists($target) && strlen(trim(CFile::get($target))) > 0 && !$this->option('force')) {
            $this->error('CLAUDE.md already exists at ' . $target);
            $this->line('Pass --force to overwrite it.');

            return CConsole::FAILURE_EXIT;
        }

        $stubFile = CF::findFile('stubs', 'claude', true, 'stub');
        if (!$stubFile) {
            $this->error('claude stub not found');

            return CConsole::FAILURE_EXIT;
        }

        $content = CFile::get($stubFile);
        $content = str_replace('{app}', $appCode, $content);
        $content = str_replace('{prefix}', $this->resolvePrefix($appCode), $content);

        CFile::put($target, $content);

        $this->info('CLAUDE.md created at ' . $target);
        $this->line('Sections are intentionally empty — describe only what is specific to this application.');

        return CConsole::SUCCESS_EXIT;
    }

    /**
     * Application code taken from the working directory rather than the configured
     * domain, so the command describes the application the user is standing in.
     *
     * @return null|string
     */
    protected function resolveAppCode() {
        $appsRoot = c::fixPath(DOCROOT . 'application');
        $cwd = c::fixPath(getcwd());

        if (!cstr::startsWith($cwd, $appsRoot)) {
            return null;
        }

        $relative = trim(substr($cwd, strlen($appsRoot)), DS);
        if (strlen($relative) == 0) {
            return null;
        }

        $segments = explode(DS, $relative);

        return $segments[0];
    }

    /**
     * Class prefix of the application, falling back to the first two letters of its
     * code when config is not reachable.
     *
     * @param string $appCode
     *
     * @return string
     */
    protected function resolvePrefix($appCode) {
        $prefix = (string) CF::config('app.prefix');
        if (strlen($prefix) > 0) {
            return $prefix;
        }

        return cstr::toupper(substr($appCode, 0, 2));
    }
}
