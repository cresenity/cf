<?php

/**
 * @see CApp
 */
trait CApp_Concern_RendererTrait {
    /**
     * @var bool
     */
    protected $rendered = false;

    /**
     * @var null|array
     */
    protected $viewData = null;

    /**
     * @param array $options
     *
     * @return string
     */
    public function renderContent($options = []) {
        /** @var CApp $this */
        $viewData = $this->getViewData();

        return carr::get($viewData, 'content');
    }

    /**
     * @param null|array|Closure|CNavigation_Nav|string $nav
     * @param mixed                                      $renderer
     *
     * @return string
     */
    public function renderNavigation($nav = null, $renderer = null) {
        /** @var CApp $this */
        if ($nav == null) {
            $nav = $this->nav;
        }
        if ($nav == null) {
            //Lewat CNavigation_Data::get() agar navigation callback ikut
            //diterapkan. Sebelumnya jalur ini membaca $this->nav langsung,
            //sehingga CNavigation_Data::setNavigationCallback() tidak pernah
            //berpengaruh pada @CAppNav — yaitu cara hampir semua aplikasi
            //merender sidenav-nya. Hasilnya di-memoisasi per domain, jadi
            //tidak menambah pembacaan berkas.
            $nav = CNavigation_Data::get();
        }

        if (!$nav instanceof CNavigation_Nav) {
            /** @var CApp $this */
            $nav = $this->resolveNav($nav);
        }
        /** @var CNavigation_Nav $nav */
        if ($renderer != null) {
            $this->setNavRenderer($renderer);
        }

        $renderer = $this->getNavRenderer();
        $renderer = CNavigation::manager()->resolveRenderer($renderer);

        return $renderer->render($nav->getData());
    }

    /**
     * @param array $options
     *
     * @return string
     */
    public function renderStyles($options = []) {
        /** @var CApp $this */
        $viewData = $this->getViewData();
        $cresCss = curl::base() . 'media/js/cres/dist/cres.css?v=' . CManager_Asset_Helper::getVersionForFile(DOCROOT . 'media/js/cres/dist/cres.css');

        $alpineJs = curl::base() . 'media/js/libs/alpine.js?v=' . CManager_Asset_Helper::getVersionForFile(DOCROOT . 'media/js/libs/alpine.js');
        $alpineScript = '<script src="' . $alpineJs . '" defer></script>';
        $cresStyle = '<link href="' . $cresCss . '" rel="stylesheet" />';
        $cresStyleIsEnabled = !!CF::config('cresjs.style.enable');
        $cresStyleInline = $cresStyleIsEnabled ? PHP_EOL . '<style id="cres-styles"></style>' : '';

        $allStyles = carr::get($viewData, 'head_client_script');

        return <<<HTML
<style>
    [cres\:loading], [cres\:loading\.delay], [cres\:loading\.inline-block], [cres\:loading\.inline], [cres\:loading\.block], [cres\:loading\.flex], [cres\:loading\.table], [cres\:loading\.grid] {
        display: none;
    }
    [cres\:offline] {
        display: none;
    }
    [cs-cloak] {
        display: none!important;
    }
    [cres\:dirty]:not(textarea):not(input):not(select) {
        display: none;
    }
    input:-webkit-autofill, select:-webkit-autofill, textarea:-webkit-autofill {
        animation-duration: 50000s;
        animation-name: cresautofill;

    }
    @keyframes cresautofill { from {} }
</style>
{$cresStyle}{$cresStyleInline}
{$allStyles}
HTML;
    }

    /**
     * @param array $options
     *
     * @return string
     */
    public function renderScripts($options = []) {
        $viewData = $this->getViewData();
        $endClientScript = carr::get($viewData, 'end_client_script', '');
        $readyClientScript = carr::get($viewData, 'ready_client_script', '');
        $loadClientScript = carr::get($viewData, 'load_client_script', '');
        $js = carr::get($viewData, 'js', '');
        $customJs = carr::get($viewData, 'custom_js', '');

        $alpineJs = curl::base() . 'media/js/libs/alpine.js?v=' . CManager_Asset_Helper::getVersionForFile(DOCROOT . 'media/js/libs/alpine.js');
        $alpineScript = '<script src="' . $alpineJs . '"></script>';

        $pushesScript = $this->yieldPushContent('capp-script');

        $cresJsFile = 'cres.js';
        $cresJs = curl::base() . 'media/js/cres/dist/' . $cresJsFile . '?v=' . CManager_Asset_Helper::getVersionForFile(DOCROOT . 'media/js/cres/dist/' . $cresJsFile);

        $notificationScript = '';
        if ($this->notification()->isEnabled()) {
            $notificationScript = c::view('cresenity.notification.javascript')->render();
        }

        return <<<HTML
            {$endClientScript}
            <script defer src="{$cresJs}"></script>
            {$notificationScript}
            <script>
                {$js}
                {$readyClientScript}
                if (window) {
                    window.onload = function () {
                        {$loadClientScript}
                    }
                }
                {$customJs}
            </script>
            {$pushesScript}

HTML;
    }

    /**
     * @param array $options
     *
     * @return string
     */
    public function renderTitle($options = []) {
        $viewData = $this->getViewData();

        return carr::get($viewData, 'title');
    }

    /**
     * @param array $options
     *
     * @return string
     */
    public function renderPageTitle($options = []) {
        $viewData = $this->getViewData();

        return carr::get($viewData, 'pageTitle');
    }

    /**
     * @param array $options
     *
     * @return string
     */
    public function renderMessages($options = []) {
        return CApp_Message::flashAll();
    }

    /**
     * @param array $options
     *
     * @return string
     */
    public function renderSeo($options = []) {
        /** @var CApp $this */
        return $this->seo()->generate();
    }

    /**
     * @return array
     */
    public function getViewData() {
        /** @var CApp $this */
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
            if (CF::config('app.javascript.minify')) {
                $this->js = $this->minifyJavascript($this->js);
            }
            $viewData['content'] = $this->content;

            $viewData['title'] = $this->title;
            $viewData['pageTitle'] = $this->title;
            $asset = CManager::asset();

            $additional_js = '';

            $js = '';

            $js .= PHP_EOL . $this->js . $additional_js;
            $jsScriptFile = '';
            $jsScriptFile = PHP_EOL . '<script>' . $asset->varJs() . '</script>';
            $jsScriptFile .= PHP_EOL . '<script>if(typeof define === "function") define=undefined;</script>';
            $jsScriptFile .= PHP_EOL . $asset->render(CManager_Asset::POS_END, CManager_Asset::TYPE_JS_FILE);

            $js = $asset->wrapJs($js, true);

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

    /**
     * @return void
     */
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

    /**
     * @return bool
     */
    public function rendered() {
        return $this->rendered;
    }

    /**
     * Render the html of this.
     *
     * @throws Exception
     * @throws CApp_Exception
     *
     * @return string
     */
    public function render() {
        /** @var CApp $this */
        if (!CF::isTesting()) {
            if ($this->rendered) {
                throw new CApp_Exception('CApp already Rendered');
            }
        }
        /** @var CApp $this */
        if (CDebug::bar()->isEnabled()) {
            CDebug::bar()->populateAssets();
        }
        $this->rendered = true;

        CFEvent::run('CApp.beforeRender');
        $this->registerCoreModules();

        if (c::request()->ajax()) {
            return $this->toJson();
        }

        if (CSession::sessionConfigured()) {
            CView::factory()->share(
                'errors',
                c::session()->get('errors') ?: new CBase_ViewErrorBag()
            );
        }

        $viewData = $this->getViewData();
        $v = $this->getView();

        $v->set($viewData);

        return $v->render();
    }
}
