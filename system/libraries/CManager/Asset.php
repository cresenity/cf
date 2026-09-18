<?php

defined('SYSPATH') or die('No direct access allowed.');

class CManager_Asset {
    /**
     * POS CONST.
     */
    const POS_HEAD = 'head';

    const POS_BEGIN = 'begin';

    const POS_END = 'end';

    const POS_READY = 'ready';

    const POS_LOAD = 'load';

    /**
     * TYPE CONST.
     */
    const TYPE_JS_FILE = 'js_file';

    const TYPE_JS = 'js';

    const TYPE_CSS_FILE = 'css_file';

    const TYPE_CSS = 'css';

    const TYPE_META = 'meta';

    const TYPE_LINK = 'link';

    const TYPE_PLAIN = 'plain';

    /**
     * Array of all type script.
     *
     * @var array
     */
    public static $allType = [
        self::TYPE_JS_FILE,
        self::TYPE_JS,
        self::TYPE_CSS_FILE,
        self::TYPE_CSS,
        self::TYPE_META,
        self::TYPE_JS,
        self::TYPE_LINK,
        self::TYPE_PLAIN,
    ];

    /**
     * @var CManager_Asset_Container_Theme
     */
    protected $themeContainer;

    /**
     * @var CManager_Asset_Container_RunTime
     */
    protected $runTimeContainer;

    /**
     * @var CManager_Asset_Module
     */
    protected $module;

    public function __construct() {
        $this->runTimeContainer = new CManager_Asset_Container_RunTime();
        $this->themeContainer = new CManager_Asset_Container_Theme();
        $this->module = new CManager_Asset_Module();
    }

    public function reset() {
        $this->runTimeContainer->reset();
        $this->themeContainer->reset();
        $this->module->reset();
    }

    public static function allAvailablePos() {
        return [self::POS_HEAD, self::POS_BEGIN, self::POS_END, self::POS_LOAD, self::POS_READY];
    }

    public static function allAvailableType() {
        return [self::TYPE_JS_FILE, self::TYPE_JS, self::TYPE_CSS_FILE, self::TYPE_CSS, self::TYPE_META, self::TYPE_LINK];
    }

    /**
     * @return CManager_Asset_Container_RunTime
     */
    public function runTime() {
        return $this->runTimeContainer;
    }

    /**
     * @return CManager_Asset_Container_Theme
     */
    public function theme() {
        return $this->themeContainer;
    }

    /**
     * @return CManager_Asset_Module
     */
    public function &module() {
        return $this->module;
    }

    public function getAllCssFileUrl() {
        $runTimeCss = $this->runTimeContainer->getAllCssFileUrl();
        $moduleRunTimeCss = $this->module->getRunTimeContainer()->getAllCssFileUrl();

        return array_merge($this->themeFileUrls(static::TYPE_CSS_FILE), $moduleRunTimeCss, $runTimeCss);
    }

    public function getAllJsFileUrl() {
        $moduleRunTimeJs = $this->module->getRunTimeContainer()->getAllJsFileUrl();
        $runTimeJs = $this->runTimeContainer->getAllJsFileUrl();

        return array_merge($this->themeFileUrls(static::TYPE_JS_FILE), $moduleRunTimeJs, $runTimeJs);
    }

    public function varJs() {
        $varJs = 'window.capp = ' . json_encode(c::app()->variables()) . ';';

        return $varJs;
    }

    public function wrapJs($js, $documentReady = false) {
        $js_before = '';

        if ($documentReady) {
            $js = 'jQuery(document).ready(function(){' . $js . '});';
        }
        // $js .= "
        //     if (typeof cappStartedEventInitilized === 'undefined') {
        //         cappStartedEventInitilized=false;
        //      }
        //     if(!cappStartedEventInitilized) {
        //         var evt = document.createEvent('Events');
        //         evt.initEvent('capp-started', false, true, window, 0);
        //         cappStartedEventInitilized=true;
        //         document.dispatchEvent(evt);
        //     }

        // ";

        $js .= CJavascript::compile();
        $bar = CDebug::bar();
        if ($bar->isEnabled()) {
            $js .= $bar->getJavascriptReplaceCode();
        }

        return $js_before . $js . PHP_EOL . ';' . PHP_EOL;
    }

    public function renderJsRequire($js, $require = 'cresenity.cf.requireJs') {
        //return CClientModules::instance()->require_js($js);
        $app = CApp::instance();

        $moduleThemejsFiles = $this->module->getThemeContainer()->jsFiles();
        $themejsFiles = $this->themeContainer->jsFiles();
        $moduleRunTimejsFiles = $this->module->getRunTimeContainer()->jsFiles();
        $runTimejsFiles = $this->runTimeContainer->jsFiles();

        $jsFiles = array_merge($moduleThemejsFiles, $themejsFiles, $moduleRunTimejsFiles, $runTimejsFiles);

        $jsOpen = '';
        $jsClose = '';
        $jsBefore = '';
        $i = 0;
        $manager = CManager::instance();

        if ($manager->getUseRequireJs()) {
            foreach ($jsFiles as $f) {
                $urlJsFile = CManager_Asset_Helper::urlJsFile($f);

                $jsOpen .= str_repeat("\t", $i) . $require . "(['" . $urlJsFile . "'],function(){" . PHP_EOL;

                $jsClose .= '})';
                $i++;
            }
        }
        $jsBefore = c::request()->ajax() ? '' : $this->varJs();

        return $jsBefore . $this->wrapJs($jsOpen . $js . $jsClose);
    }

    /**
     * Skrip tema (modul tema + tema) untuk satu posisi, urutan persis seperti yang dirender.
     *
     * @param string $pos
     *
     * @return array
     */
    protected function themeScripts($pos) {
        $themeScriptArray = [];
        $themeScriptArray = carr::merge($themeScriptArray, $this->module->getThemeContainer()->getScripts($pos));
        $themeScriptArray = carr::merge($themeScriptArray, $this->themeContainer->getScripts($pos));

        return $themeScriptArray;
    }

    /**
     * Ganti daftar berkas tema dengan satu bundel bila `assets.*.compile` menyala.
     *
     * @param array $themeScriptArray
     *
     * @return array
     */
    protected function compileThemeScripts(array $themeScriptArray) {
        if (CF::config('assets.css.compile', false)) {
            $cssScriptArray = carr::get($themeScriptArray, static::TYPE_CSS_FILE, []);
            if (count($cssScriptArray) > 0) {
                $themeScriptArray[static::TYPE_CSS_FILE] = [$this->compileCss($cssScriptArray)];
            }
        }
        if (CF::config('assets.js.compile', false)) {
            $jsScriptArray = carr::get($themeScriptArray, static::TYPE_JS_FILE, []);
            if (count($jsScriptArray) > 0) {
                $themeScriptArray[static::TYPE_JS_FILE] = [$this->compileJs($jsScriptArray)];
            }
        }

        return $themeScriptArray;
    }

    /**
     * URL berkas tema seperti yang dirender halaman: bundel bila compile menyala, sehingga daftar aset
     * respons ajax cocok dengan tag <script>/<link> yang sudah ada dan tidak memuat ulang skrip tema.
     *
     * @param string $type
     *
     * @return string[]
     */
    protected function themeFileUrls($type) {
        $configType = $type == static::TYPE_CSS_FILE ? 'css' : 'js';
        if (!CF::config('assets.' . $configType . '.compile', false)) {
            $method = $type == static::TYPE_CSS_FILE ? 'getAllCssFileUrl' : 'getAllJsFileUrl';

            return array_merge($this->module->getThemeContainer()->$method(), $this->themeContainer->$method());
        }

        $urls = [];
        foreach (static::allAvailablePos() as $pos) {
            $scripts = $this->compileThemeScripts($this->themeScripts($pos));
            foreach (carr::get($scripts, $type, []) as $script) {
                if ($script instanceof CManager_Asset_FileAbstract) {
                    $urls[] = $script->getUrl();
                } else {
                    $urls[] = $type == static::TYPE_CSS_FILE ? CManager_Asset_Helper::urlCssFile($script) : CManager_Asset_Helper::urlJsFile($script);
                }
            }
        }

        return $urls;
    }

    public function render($pos, $type = null) {
        $moduleRunTimeScripts = $this->module->getRunTimeContainer()->getScripts($pos);
        $runTimeScripts = $this->runTimeContainer->getScripts($pos);
        $runtimeScriptArray = [];
        $runtimeScriptArray = carr::merge($runtimeScriptArray, $moduleRunTimeScripts);
        $runtimeScriptArray = carr::merge($runtimeScriptArray, $runTimeScripts);

        $themeScriptArray = $this->compileThemeScripts($this->themeScripts($pos));

        $scriptArray = carr::merge($themeScriptArray, $runtimeScriptArray);
        $script = '';
        $manager = CManager::instance();
        if ($type == null) {
            $type = self::$allType;
        }
        if (!is_array($type)) {
            $type = [$type];
        }
        foreach ($scriptArray as $scriptType => $scriptValueArray) {
            if (in_array($scriptType, $type)) {
                foreach ($scriptValueArray as $scriptValue) {
                    switch ($scriptType) {
                        case self::TYPE_JS_FILE:
                            if ($scriptValue instanceof CManager_Asset_File_JsFile) {
                                $script .= $scriptValue->render() . PHP_EOL;
                            } else {
                                $urlJsFile = CManager_Asset_Helper::urlJsFile($scriptValue);
                                $script .= '<script src="' . $urlJsFile . '"></script>' . PHP_EOL;
                            }

                            break;
                        case self::TYPE_CSS_FILE:
                            if ($scriptValue instanceof CManager_Asset_File_CssFile) {
                                $script .= $scriptValue->render() . PHP_EOL;
                            } else {
                                $urlCssFile = CManager_Asset_Helper::urlCssFile($scriptValue);
                                $script .= '<link href="' . $urlCssFile . '" rel="stylesheet" />' . PHP_EOL;
                            }

                            break;
                        case self::TYPE_JS:
                            $script .= '<script>' . $scriptValue . '</script>' . PHP_EOL;

                            break;
                        case self::TYPE_CSS:
                            $script .= '<style>' . $scriptValue . '</style>' . PHP_EOL;

                            break;
                        case self::TYPE_PLAIN:
                            $script .= $scriptValue . PHP_EOL;

                            break;
                    }
                }
            }
        }

        return $script;
    }

    public function compileCss($files) {
        $options = [];
        $options['type'] = 'css';
        $compiler = new CManager_Asset_Compiler($files, $options);

        return $compiler->compile();
    }

    public function compileJs($files) {
        $options = [];
        $options['type'] = 'js';
        $compiler = new CManager_Asset_Compiler($files, $options);

        return $compiler->compile();
    }

    /**
     * @return CImage_OptimizerChain
     */
    public function optimizer() {
        return new CImage_OptimizerChain();
    }

    public function findMediaPath($path) {
        return CF::findFile('media', $path);
    }

    public function optimize($path) {
        if (!CFile::exists($path)) {
            $path = $this->findMediaPath($path);
        }
        if (!CFile::exists($path)) {
            throw new Exception('Path ' . $path . 'not exists');
        }

        return $this->optimizer()->optimize($path);
    }
}
