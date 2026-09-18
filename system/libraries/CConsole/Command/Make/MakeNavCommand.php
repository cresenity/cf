<?php

/**
 * Description of MakeNavCommand
 */
class CConsole_Command_Make_MakeNavCommand extends CConsole_Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:nav {nav}';

    public function handle() {
        CConsole::domainRequired($this);
        $nav = $this->argument('nav');
        $navPath = c::fixPath(CF::appDir()) . 'default' . DS . 'navs' . DS;
        if (!CFile::isDirectory($navPath)) {
            CFile::makeDirectory($navPath);
        }
        $navFile = $navPath . $nav . EXT;
        if (file_exists($navFile)) {
            $this->info('Nav ' . $nav . ' already created, no changes');
            return CConsole::SUCCESS_EXIT;
        }
        $stubFile = CF::findFile('stubs', 'nav', true, 'stub');
        if (!$stubFile) {
            $this->error('nav stub not found');
            exit(CConsole::FAILURE_EXIT);
        }
        $content = CFile::get($stubFile);
        CFile::put($navFile, $content);

        $this->info('Nav ' . $nav . ' created on:' . $navFile);
    }
}
