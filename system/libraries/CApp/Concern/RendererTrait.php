<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @see CApp
 * @since Jul 27, 2019, 10:23:46 PM
 */
trait CApp_Concern_RendererTrait {
    protected $rendered = false;

    protected $viewData = null;

    public function renderContent($options = []) {
        /** @var CApp $this */
        $viewData = $this->getViewData();

        return carr::get($viewData, 'content');
    }

    public function renderNavigation($nav = null, $renderer = null) {
        /** @var CApp $this */
        // if ($expression != null) {
        //     $expression = str_replace(['(', ')'], '', $expression);
        //     $expression = str_replace(['"', '\''], '', $expression);
        //     $expression = str_replace(',', ' ', $expression);
        // }

        if ($nav == null) {
            $nav = $this->nav;
        }

        /** @var CApp $this */
        $nav = $this->resolveNav($nav);

        if ($renderer != null) {
            $this->setNavRenderer($renderer);
        }

        $renderer = $this->resolveNavRenderer();

        return $renderer->render($nav);
    }

    public function renderStyles($options = []) {
        /** @var CApp $this */
        $viewData = $this->getViewData();
        $cresCss = curl::base() . 'media/js/cres/dist/cres.css?v=' . md5(CFile::lastModified(DOCROOT . 'media/js/cres/dist/cres.css'));

        $alpineJs = curl::base() . 'media/js/libs/alpine.js?v=' . md5(CFile::lastModified(DOCROOT . 'media/js/libs/alpine.js'));
        $alpineScript = '<script src="' . $alpineJs . '" defer></script>';
        $cresStyle = '<link href="' . $cresCss . '" rel="stylesheet" />' . PHP_EOL;

        $allStyles = carr::get($viewData, 'head_client_script');

        return <<<HTML
<style>
    [cres\:loading], [cres\:loading\.delay], [cres\:loading\.inline-block], [cres\:loading\.inline], [cres\:loading\.block], [cres\:loading\.flex], [cres\:loading\.table], [cres\:loading\.grid] {
        display: none;
    }
    [cres\:offline] {
        display: none;
    }
    [cres\:dirty]:not(textarea):not(input):not(select) {
        display: none;
    }
    input:-webkit-autofill, select:-webkit-autofill, textarea:-webkit-autofill {
        animation-duration: 50000s;
        animation-name: livecfautofill;
    }
    @keyframes livecfautofill { from {} }
</style>
${cresStyle}
${allStyles}
${alpineScript}
HTML;
    }

    public function renderScripts($options = []) {
        /** @var CApp $this */
        $viewData = $this->getViewData();
        $endClientScript = carr::get($viewData, 'end_client_script', '');
        $readyClientScript = carr::get($viewData, 'ready_client_script', '');
        $loadClientScript = carr::get($viewData, 'load_client_script', '');
        $js = carr::get($viewData, 'js', '');
        $customJs = carr::get($viewData, 'custom_js', '');

        $alpineJs = curl::base() . 'media/js/libs/alpine.js?v=' . md5(CFile::lastModified(DOCROOT . 'media/js/libs/alpine.js'));
        $alpineScript = '<script src="' . $alpineJs . '"></script>';

        $pushesScript = $this->yieldPushContent('capp-script');

        $cresJs = curl::base() . 'media/js/cres/dist/cres.js?v=' . md5(CFile::lastModified(DOCROOT . 'media/js/cres/dist/cres.js'));

        return <<<HTML
            ${endClientScript}
            <script src="${cresJs}"></script>
            <script>

                if (window.Alpine) {
                    /* Defer showing the warning so it doesn't get buried under downstream errors. */
                    document.addEventListener("DOMContentLoaded", function () {
                        setTimeout(function() {
                            console.warn("Cresenity: It looks like AlpineJS has already been loaded. Make sure Creseniity\'s scripts are loaded before Alpine.")
                        })
                    });
                }
                /* Make Alpine wait until Livewire is finished rendering to do its thing. */
                window.deferLoadingAlpine = function (callback) {
                    window.addEventListener('cresenity:ui:start', function () {
                        callback();
                    });
                };
                document.addEventListener("DOMContentLoaded", function () {
                    window.cresenity.ui.start();
                });

            </script>
            <script src="${alpineJs}"></script>
            <script>
                ${js}
                ${readyClientScript}
                if (window) {
                    window.onload = function () {
                        ${loadClientScript}
                    }
                }
                ${customJs}
            </script>
            <script>
                ${pushesScript}
            </script>

HTML;
    }

    public function renderTitle($options = []) {
        $viewData = $this->getViewData();

        return carr::get($viewData, 'title');
    }

    public function renderPageTitle($options = []) {
        $viewData = $this->getViewData();

        return carr::get($viewData, 'pageTitle');
    }

    public function renderMessages($options = []) {
        return CApp_Message::flashAll();
    }

    public function getViewData() {
        if ($this->viewData == null) {
            $theme_path = '';

            $theme = CManager::theme()->getCurrentTheme();

            $themeFile = CF::getFile('themes', $theme);
            if (file_exists($themeFile)) {
                $themeData = include $themeFile;
                $theme_path = carr::get($themeData, 'theme_path');
                if ($theme_path == null) {
                    $theme_path = '';
                } else {
                    $theme_path .= '/';
                }
            }
            $viewData = [];
            $this->content = $this->element->html();
            $this->js = $this->element->js();

            $viewData['content'] = $this->content;

            $viewData['title'] = $this->title;
            $viewData['pageTitle'] = $this->title;
            $asset = CManager::asset();

            // $css_urls = $asset->getAllCssFileUrl();
            // $js_urls = $asset->getAllJsFileUrl();
            $additional_js = '';

            $js = '';

            $js .= PHP_EOL . $this->js . $additional_js;
            $jsScriptFile = '';
            $jsScriptFile = PHP_EOL . '<script>' . $asset->varJs() . '</script>';
            $jsScriptFile .= PHP_EOL . '<script>if(typeof define === "function") define=undefined;</script>';
            //$jsScriptFile .= '<script src="/media/js/capp.js?v='.uniqid().'"></script>';
            $jsScriptFile .= PHP_EOL . $asset->render(CManager_Asset::POS_END, CManager_Asset::TYPE_JS_FILE);

            $js = $asset->wrapJs($js, true);

            /*
            if (!$this->isUseRequireJs()) {
                $bar = CDebug::bar();
                if ($bar->isEnabled()) {
                    $js .= $bar->getJavascriptReplaceCode();
                }
            }
            */

            $viewData['js'] = $js;

            $viewData['css_hash'] = '';
            $viewData['js_hash'] = '';

            $viewData['theme'] = $theme;
            $viewData['theme_path'] = $theme_path;
            $viewData['themePath'] = $theme_path;
            $viewData['head_client_script'] = $asset->render('head');
            $viewData['begin_client_script'] = $asset->render('begin');
            $viewData['end_client_script'] = $jsScriptFile;
            $viewData['load_client_script'] = $asset->render('load');
            $viewData['ready_client_script'] = $asset->render('ready');
            $viewData['custom_js'] = $this->custom_js;
            $viewData['custom_header'] = $this->custom_header;
            $viewData['custom_footer'] = $this->custom_footer;
            $viewData['show_breadcrumb'] = $this->showBreadcrumb;
            $viewData['showBreadcrumb'] = $this->showBreadcrumb;
            $viewData['show_title'] = $this->showTitle;
            $viewData['showTitle'] = $this->showTitle;
            $viewData['breadcrumb'] = $this->getBreadcrumb();
            $viewData['additional_head'] = $this->additional_head;
            $viewData['custom_data'] = $this->custom_data;
            $viewData['login_required'] = $this->isAuthEnabled();
            $viewData['loginRequired'] = $this->isAuthEnabled();
            $viewData['isAuthEnabled'] = $this->isAuthEnabled();

            //deprecated view data
            $viewData['header_body'] = '';
            $viewData['headerBody'] = '';

            $viewData = array_merge($this->data, $viewData);

            $this->viewData = $viewData;
        }

        return $this->viewData;
    }

    public function allModuleData() {
        $allModule = CManager::asset()->module()->allModules();
        foreach ($allModule as $moduleName => $module) {
            foreach ($module as $type => $urls) {
                foreach ($urls as $indexUrl => $url) {
                    if ($type == 'js') {
                        $allModule[$moduleName][$type][$indexUrl] = CManager_Asset_Helper::urlJsFile($url);
                    }
                    if ($type == 'css') {
                        $allModule[$moduleName][$type][$indexUrl] = CManager_Asset_Helper::urlCssFile($url);
                    }
                }
            }
        }
    }

    public function rendered() {
        return $this->rendered;
    }

    /**
     * Render the html of this.
     *
     * @throws CException
     * @throws CApp_Exception
     *
     * @return void
     */
    public function render() {
        /** @var CApp $this */
        if ($this->rendered) {
            throw new CApp_Exception('CApp already Rendered');
        }
        $this->rendered = true;

        $this->registerCoreModules();

        CFEvent::run('CApp.beforeRender');

        if (c::request()->ajax()) {
            return $this->json();
        }

        CView::factory()->share(
            'errors',
            CSession::instance()->get('errors') ?: new CBase_ViewErrorBag()
        );

        $viewData = $this->getViewData();
        $v = $this->getView();

        $v->set($viewData);

        return $v->render();
    }
}
