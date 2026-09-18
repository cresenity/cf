<?php

/**
 * Description of MakeThemeCommand
 */
class CConsole_Command_Make_MakeThemeCommand extends CConsole_Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:theme {theme}';

    public function handle() {
        CConsole::domainRequired($this);
        $theme = $this->argument('theme');
        $themePath = c::fixPath(CF::appDir()) . 'default' . DS . 'themes' . DS;
        $mediaPath = c::fixPath(CF::appDir()) . 'default' . DS . 'media' . DS;
        if (!CFile::isDirectory($themePath)) {
            CFile::makeDirectory($themePath);
        }
        $themeFile = $themePath . $theme . EXT;
        if (file_exists($themeFile)) {
            $this->info('Theme' . $theme . ' already created, no changes');
            return CConsole::SUCCESS_EXIT;
        }
        $stubFile = CF::findFile('stubs', 'theme', true, 'stub');
        if (!$stubFile) {
            $this->error('theme stub not found');
            exit(CConsole::FAILURE_EXIT);
        }

        $jsDir = $mediaPath . 'js' . DS;
        $cssDir = $mediaPath . 'css' . DS;
        $appJsFile = $jsDir . $theme . '.js';
        $appCssFile = $cssDir . $theme . '.css';

        if (!CFile::exists($appCssFile)) {
            CFile::put($appCssFile, '');
            $this->info('Css File :' . $appCssFile . ' created');
        }
        if (!CFile::exists($appJsFile)) {
            CFile::put($appJsFile, '');
            $this->info('Js File :' . $appJsFile . ' created');
        }

        $content = CFile::get($stubFile);

        $content = str_replace('{app.css}', $theme . '.css', $content);
        $content = str_replace('{app.js}', $theme . '.js', $content);
        CFile::put($themeFile, $content);

        $this->info('Theme ' . $theme . ' created on:' . $themeFile);
    }
}
