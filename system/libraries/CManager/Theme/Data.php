<?php

/**
 * Description of Data
 */
class CManager_Theme_Data {
    protected $modules;

    protected $css;

    protected $js;

    protected $data;

    public function __construct($theme) {
        $themeFile = CF::getFile('themes', $theme);
        if (!CFile::exists($themeFile)) {
            throw new Exception('Theme ' . $theme . ' not exists');
        }
        $themeData = include $themeFile;
        $this->modules = carr::get($themeData, 'modules', carr::get($themeData, 'client_module'));
        $this->css = carr::get($themeData, 'css');
        $this->js = carr::get($themeData, 'js');
        $this->data = carr::get($themeData, 'data');
    }

    public function getModules() {
        return $this->modules;
    }

    public function getCss() {
        return $this->css;
    }

    public function getJs() {
        return $this->js;
    }

    public function getData() {
        return $this->data;
    }
}
