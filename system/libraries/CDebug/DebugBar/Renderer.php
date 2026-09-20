<?php

defined('SYSPATH') or die('No direct access allowed.');

use DebugBar\DataCollector\Renderable;
use DebugBar\DataCollector\AssetProvider;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CDebug_DebugBar_Renderer {
    const REPLACEABLE_TAG = '<!-- CAPP-DEBUGBAR-CODE -->';

    const REPLACEABLE_JS_TAG = '/* CAPP-DEBUGBAR-CODE */';

    /**
     * @var CDebug_DebugBar
     */
    protected $debugBar;

    /**
     * @var string
     */
    protected $javascriptClass = 'PhpDebugBar.DebugBar';

    /**
     * @var string
     */
    protected $variableName = 'phpdebugbar';

    protected $controls = [];

    protected $ignoredCollectors = [];

    protected $ajaxHandlerClass = 'PhpDebugBar.AjaxHandler';

    protected $ajaxHandlerBindToJquery = true;

    protected $ajaxHandlerBindToXHR = false;

    protected $ajaxHandlerAutoShow = true;

    protected $openHandlerClass = 'PhpDebugBar.OpenHandler';

    protected $openHandlerUrl;

    protected $cssFiles = [
        'debug/debugbar.css',
        'debug/debugbar/widgets.css',
        'debug/debugbar/openhandler.css',
        'debug/debugbar/font-awesome/css/font-awesome.min.css',
        'debug/debugbar/highlightjs/styles/github.css',
        'debug/debugbar-custom.css',
    ];

    protected $jsFiles = [
        'debug/debugbar.js',
        'debug/debugbar/widgets.js',
        'debug/debugbar/openhandler.js',
        'debug/debugbar/highlightjs/highlight.pack.js',
    ];

    public function __construct(CDebug_DebugBar $bar) {
        $this->debugBar = $bar;
    }

    public function populateAssets() {
        $cssFiles = $this->cssFiles;
        $jsFiles = $this->jsFiles;
        $inlineCss = [];
        $inlineJs = [];
        $inlineHead = [];
        $additionalAssets = [];
        // finds assets provided by collectors
        foreach ($this->debugBar->getCollectors() as $collector) {
            if (($collector instanceof AssetProvider) && !in_array($collector->getName(), $this->ignoredCollectors)) {
                $additionalAssets[] = $collector->getAssets();
            }
        }
        foreach ($additionalAssets as $assets) {
            if (isset($assets['css'])) {
                $cssFiles = array_merge($cssFiles, $assets['css']);
            }
            if (isset($assets['js'])) {
                $jsFiles = array_merge($jsFiles, $assets['js']);
            }
            if (isset($assets['inline_css'])) {
                $inlineCss = array_merge($inlineCss, (array) $assets['inline_css']);
            }
            if (isset($assets['inline_js'])) {
                $inlineJs = array_merge($inlineJs, (array) $assets['inline_js']);
            }
            if (isset($assets['inline_head'])) {
                $inlineHead = array_merge($inlineHead, (array) $assets['inline_head']);
            }
        }
        // Deduplicate files
        $cssFiles = array_unique($cssFiles);
        $jsFiles = array_unique($jsFiles);
        $assetManager = CManager::asset();
        $assetManager->runTime()->registerCssFiles($cssFiles);
        $assetManager->runTime()->registerJsFiles($jsFiles);
        $assetManager->runTime()->registerCssInlines($inlineCss);
        $assetManager->runTime()->registerJsInlines($inlineJs);
        $assetManager->runTime()->registerPlains($inlineHead);
    }

    public function getJavascriptReplaceCode() {
        return self::REPLACEABLE_JS_TAG;
    }

    /**
     * @inheritdoc
     */
    public function renderHead() {
        $html = '';

        foreach ($this->cssFiles as $css) {
            $cssRoute = CManager_Asset_Helper::urlCssFile(CManager_Asset_Helper::fullpathCssFile($css));
            $html .= "<link rel='stylesheet' type='text/css' property='stylesheet' href='{$cssRoute}'>" . PHP_EOL;
        }
        $jquery = curl::base() . 'media/js/libs/jquery-3.3.1/jquery-3.3.1.min.js';
        $html .= "<script type='text/javascript' src='{$jquery}'></script>" . PHP_EOL;
        foreach ($this->jsFiles as $js) {
            $jsRoute = CManager_Asset_Helper::urlJsFile(CManager::asset()->runTime()->fullpathJsFile($js));
            $html .= "<script type='text/javascript' src='{$jsRoute}'></script>" . PHP_EOL;
        }
        // finds assets provided by collectors
        foreach ($this->debugBar->getCollectors() as $collector) {
            if (($collector instanceof AssetProvider) && !in_array($collector->getName(), $this->ignoredCollectors)) {
                $assets = $collector->getAssets();
                foreach (carr::get($assets, 'css', []) as $css) {
                    $cssRoute = CManager_Asset_Helper::urlCssFile(CManager_Asset_Helper::fullpathCssFile($css));
                    $html .= "<link rel='stylesheet' type='text/css' property='stylesheet' href='{$cssRoute}'>" . PHP_EOL;
                }
                foreach (carr::get($assets, 'js', []) as $js) {
                    $jsRoute = CManager_Asset_Helper::urlJsFile(CManager::asset()->runTime()->fullpathJsFile($js));
                    $html .= "<script type='text/javascript' src='{$jsRoute}'></script>" . PHP_EOL;
                }
                foreach (carr::get($assets, 'inline_head', []) as $inline) {
                    $html .= $inline . PHP_EOL;
                }
            }
        }
        $html .= '<script type="text/javascript">jQuery.noConflict(true);</script>' . "\n";

        return $html;
    }

    public function replaceJavascriptCode($string) {
        $javascriptCode = $this->getJavascriptCode();
        if (strpos($string, $this->getJavascriptReplaceCode()) !== false) {
            $string = str_replace($this->getJavascriptReplaceCode(), $javascriptCode, $string);
        } else {
            $javascriptCode = '<script>' . $javascriptCode . '</script>';

            // Try to put the js/css directly before the </head>
            $pos = strripos($string, '</head>');
            $head = $this->renderHead();
            if (false !== $pos) {
                $string = substr($string, 0, $pos) . $head . substr($string, $pos);
            } else {
                // Append the head before the widget
                $javascriptCode = $head . $javascriptCode;
            }
            $pos = strripos($string, '</body>');
            if (false !== $pos) {
                $string = substr($string, 0, $pos) . $javascriptCode . substr($string, $pos);
            } else {
                $string = $string . $javascriptCode;
            }
        }

        return $string;
    }

    /**
     * Returns the code needed to display the debug bar.
     *
     * AJAX request should not render the initialization code.
     *
     * @param bool $initialize        Whether or not to render the debug bar initialization code
     * @param bool $renderStackedData Whether or not to render the stacked data
     *
     * @return string
     */
    public function getJavascriptCode($initialize = true, $renderStackedData = true) {
        $js = '';
        if ($initialize) {
            $js = $this->getJavaScriptInitializationCode();
        }
        if ($renderStackedData && $this->debugBar->hasStackedData()) {
            foreach ($this->debugBar->getStackedData() as $id => $data) {
                $js .= $this->getAddDatasetCode($id, $data, '(stacked)');
            }
        }
        $suffix = !$initialize ? '(ajax)' : null;
        $js .= $this->getAddDatasetCode($this->debugBar->getCurrentRequestId(), $this->debugBar->getData(), $suffix);

        return $js;
    }

    /**
     * Returns the js code needed to initialize the debug bar.
     *
     * @return string
     */
    public function getJavaScriptInitializationCode() {
        $js = '';
        $js .= sprintf("var %s = new %s();\n", $this->variableName, $this->javascriptClass);
        $js .= $this->getJsControlsDefinitionCode($this->variableName);

        if ($this->ajaxHandlerClass) {
            $js .= sprintf("%s.ajaxHandler = new %s(%s, undefined, %s);\n", $this->variableName, $this->ajaxHandlerClass, $this->variableName, $this->ajaxHandlerAutoShow ? 'true' : 'false');
            if ($this->ajaxHandlerBindToXHR) {
                $js .= sprintf("%s.ajaxHandler.bindToXHR();\n", $this->variableName);
            } elseif ($this->ajaxHandlerBindToJquery) {
                $js .= sprintf("if (jQuery) %s.ajaxHandler.bindToJquery(jQuery);\n", $this->variableName);
            }
        }
        if ($this->openHandlerUrl !== null) {
            $js .= sprintf("%s.setOpenHandler(new %s(%s));\n", $this->variableName, $this->openHandlerClass, json_encode(['url' => $this->openHandlerUrl]));
        }

        return $js;
    }

    /**
     * Returns the js code needed to initialized the controls and data mapping of the debug bar.
     *
     * Controls can be defined by collectors themselves or using {@see addControl()}
     *
     * @param string $varname Debug bar's variable name
     *
     * @return string
     */
    protected function getJsControlsDefinitionCode($varname) {
        $js = '';
        $dataMap = [];
        $excludedOptions = ['indicator', 'tab', 'map', 'default', 'widget', 'position'];
        // finds controls provided by collectors
        $widgets = [];
        foreach ($this->debugBar->getCollectors() as $collector) {
            if (($collector instanceof Renderable) && !in_array($collector->getName(), $this->ignoredCollectors)) {
                if ($w = $collector->getWidgets()) {
                    $widgets = array_merge($widgets, $w);
                }
            }
        }

        $controls = array_merge($widgets, $this->controls);

        foreach (array_filter($controls) as $name => $options) {
            $opts = array_diff_key($options, array_flip($excludedOptions));
            if (isset($options['tab']) || isset($options['widget'])) {
                if (!isset($opts['title'])) {
                    $opts['title'] = ucfirst(str_replace('_', ' ', $name));
                }
                $js .= sprintf(
                    "%s.addTab(\"%s\", new %s({%s%s}));\n",
                    $varname,
                    $name,
                    isset($options['tab']) ? $options['tab'] : 'PhpDebugBar.DebugBar.Tab',
                    substr(json_encode($opts, JSON_FORCE_OBJECT), 1, -1),
                    isset($options['widget']) ? sprintf('%s"widget": new %s()', count($opts) ? ', ' : '', $options['widget']) : ''
                );
            } elseif (isset($options['indicator']) || isset($options['icon'])) {
                $js .= sprintf(
                    "%s.addIndicator(\"%s\", new %s(%s), \"%s\");\n",
                    $varname,
                    $name,
                    isset($options['indicator']) ? $options['indicator'] : 'PhpDebugBar.DebugBar.Indicator',
                    json_encode($opts, JSON_FORCE_OBJECT),
                    isset($options['position']) ? $options['position'] : 'right'
                );
            }
            if (isset($options['map'], $options['default'])) {
                $dataMap[$name] = [$options['map'], $options['default']];
            }
        }
        // creates the data mapping object
        $mapJson = [];
        foreach ($dataMap as $name => $values) {
            $mapJson[] = sprintf('"%s": ["%s", %s]', $name, $values[0], $values[1]);
        }
        $js .= sprintf("%s.setDataMap({\n%s\n});\n", $varname, implode(",\n", $mapJson));
        // activate state restoration
        $js .= sprintf("%s.restoreState();\n", $varname);

        return $js;
    }

    public function isFileResponse($response) {
        return ($response instanceof StreamedResponse) || ($response instanceof BinaryFileResponse);
    }

    protected function modifyContentJson($json) {
        $json = array_merge($json, $this->debugBar->getDataAsHeaders('phpdebugbar', 4096, PHP_INT_MAX));

        return $json;
    }

    protected function modifyContent($output) {
        $isJson = false;
        $isJsonp = false;

        if (c::request()->ajax()) {
            $jsonpRegex = '#^/\*\*/(.+?)\((.+?)\);$#ims';
            $jsonCallback = null;
            $jsonOutput = $output;
            if (preg_match($jsonpRegex, $output, $matches)) {
                $isJsonp = true;
                $jsonOutput = carr::get($matches, 2);
                $jsonCallback = carr::get($matches, 1);
            }
            if (cstr::startsWith(trim($jsonOutput), '{') && c::request()->ajax()) {
                $json = null;

                try {
                    $json = json_decode($jsonOutput, true);
                    $json = $this->modifyContentJson($json);

                    $jsonOutput = json_encode($json);
                    $isJson = true;
                } catch (Exception $ex) {
                }
            }
            $output = $jsonOutput;
            if ($isJsonp) {
                $output = sprintf('/**/%s(%s);', $jsonCallback, $output);
            }
        }
        if (!$isJson) {
            $output = $this->replaceJavascriptCode($output);
        }

        return $output;
    }

    public function apply() {
        $renderer = $this;
        CEvent::dispatcher()->listen(CHTTP_Event_RequestHandled::class, function ($event) use ($renderer) {
            $response = $event->response;
            $jsonHelper = CHelper::json();

            if (!$renderer->isFileResponse($response)) {
                if ($response instanceof CHTTP_JsonResponse) {
                    $output = $response->getData(true);

                    try {
                        $output = $this->modifyContentJson($output);

                        $response->setData($output);
                    } catch (Exception $ex) {
                    }
                } else {
                    $original = null;
                    if ($response instanceof CHTTP_Response && $response->getOriginalContent()) {
                        $original = $response->getOriginalContent();
                    }
                    $output = $response->getContent();

                    $output = $this->modifyContent($output);

                    $response->setContent($output);
                    $response->headers->remove('Content-Length');

                    // Restore original response (eg. the View or Ajax data)
                    if ($original) {
                        $response->original = $original;
                    }
                }
                if (c::request()->ajax()) {
                    $response->headers->set('phpdebugbar-body', 1);
                }
            }
        });
    }

    /**
     * Returns the js code needed to add a dataset.
     *
     * @param string $requestId
     * @param array  $data
     * @param mixed  $suffix
     *
     * @return string
     */
    protected function getAddDatasetCode($requestId, $data, $suffix = null) {
        $js = sprintf("%s.addDataSet(%s, \"%s\"%s);\n", $this->variableName, json_encode($data), $requestId, $suffix ? ', ' . json_encode($suffix) : '');

        return $js;
    }

    /**
     * Sets the class name of the ajax handler.
     *
     * Set to false to disable
     *
     * @param string $className
     */
    public function setAjaxHandlerClass($className) {
        $this->ajaxHandlerClass = $className;

        return $this;
    }

    /**
     * Returns the class name of the ajax handler.
     *
     * @return string
     */
    public function getAjaxHandlerClass() {
        return $this->ajaxHandlerClass;
    }

    /**
     * Sets whether to call bindToJquery() on the ajax handler.
     *
     * @param bool $bind
     */
    public function setBindAjaxHandlerToJquery($bind = true) {
        $this->ajaxHandlerBindToJquery = $bind;

        return $this;
    }

    /**
     * Checks whether bindToJquery() will be called on the ajax handler.
     *
     * @return bool
     */
    public function isAjaxHandlerBoundToJquery() {
        return $this->ajaxHandlerBindToJquery;
    }

    /**
     * Sets whether to call bindToXHR() on the ajax handler.
     *
     * @param bool $bind
     */
    public function setBindAjaxHandlerToXHR($bind = true) {
        $this->ajaxHandlerBindToXHR = $bind;

        return $this;
    }

    /**
     * Checks whether bindToXHR() will be called on the ajax handler.
     *
     * @return bool
     */
    public function isAjaxHandlerBoundToXHR() {
        return $this->ajaxHandlerBindToXHR;
    }

    /**
     * Sets whether new ajax debug data will be immediately shown.  Setting to false could be useful
     * if there are a lot of tracking events cluttering things.
     *
     * @param bool $autoShow
     */
    public function setAjaxHandlerAutoShow($autoShow = true) {
        $this->ajaxHandlerAutoShow = $autoShow;

        return $this;
    }

    /**
     * Checks whether the ajax handler will immediately show new ajax requests.
     *
     * @return bool
     */
    public function isAjaxHandlerAutoShow() {
        return $this->ajaxHandlerAutoShow;
    }

    /**
     * Sets the class name of the js open handler.
     *
     * @param string $className
     */
    public function setOpenHandlerClass($className) {
        $this->openHandlerClass = $className;

        return $this;
    }

    /**
     * Returns the class name of the js open handler.
     *
     * @return string
     */
    public function getOpenHandlerClass() {
        return $this->openHandlerClass;
    }

    /**
     * Sets the url of the open handler.
     *
     * @param string $url
     */
    public function setOpenHandlerUrl($url) {
        $this->openHandlerUrl = $url;

        return $this;
    }

    /**
     * Returns the url for the open handler.
     *
     * @return string
     */
    public function getOpenHandlerUrl() {
        return $this->openHandlerUrl;
    }
}
