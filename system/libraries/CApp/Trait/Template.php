<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @deprecated since 1.6 Apr 10, 2019, 12:34:27 AM
 */
/** @phpstan-ignore-next-line */
trait CApp_Trait_Template {
    /**
     * @var null|string
     */
    protected $templateName;

    /**
     * @var array|null
     */
    protected $templateData;

    /**
     * @var string
     */
    protected $htmlOutput = '';

    /**
     * @var string
     */
    protected $jsOutput = '';

    /**
     * @var callable|null
     */
    protected $onBeforeParse = null;

    /**
     * @var CElement_PseudoElement[]
     */
    protected $sections = [];

    /**
     * @var string
     */
    protected $skeleton = '';

    /**
     * @var array
     */
    protected $helpers = [];

    /**
     * @var string
     */
    private $sectionJs = '';

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setTemplate($name) {
        $this->templateName = $name;

        return $this;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setSkeleton($name) {
        $this->skeleton = $name;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getData() {
        return $this->templateData;
    }

    /**
     * Retrieve section which declared on this template as CApp Element.
     *
     * @param string $sectionName
     *
     * @return CElement_PseudoElement
     */
    public function section($sectionName) {
        if (!isset($this->sections[$sectionName])) {
            $this->sections[$sectionName] = new CElement_PseudoElement();
        }

        return $this->sections[$sectionName];
    }

    /**
     * @param mixed $data
     *
     * @return $this
     */
    public function setData($data) {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $this->setVar($k, $v);
            }
        }

        return $this;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function getVar($key) {
        return carr::get($this->templateData, $key);
    }

    /**
     * @param string $key
     * @param mixed  $val
     *
     * @return $this
     */
    public function setVar($key, $val) {
        $this->templateData[$key] = $val;

        return $this;
    }

    /**
     * @param string $templateName
     *
     * @return string
     */
    public function getTemplatePath($templateName) {
        $viewPath = $templateName;

        return $viewPath;
    }

    /**
     * @param string   $helperName
     * @param callable $callable
     *
     * @return void
     */
    public function addHelper($helperName, callable $callable) {
        $this->helpers[$helperName] = $callable;
    }

    /**
     * @param bool $noSkeleton
     *
     * @return array
     */
    private function parseTemplate($noSkeleton = false) {
        if ($this->onBeforeParse != null) {
            $callable = $this->onBeforeParse;
            $callable();
        }
        $templateName = $this->templateName;
        $isSkeleton = false;
        if (strlen($this->skeleton) > 0 && !$noSkeleton) {
            $isSkeleton = true;
            $templateName = $this->skeleton;
        }
        $outputJs = '';
        $templateJs = '';
        $viewPath = $this->getTemplatePath($templateName);

        $view = new CTemplate($viewPath);

        $view->setBlockRoutingCallback([$this, 'getTemplatePath']);
        $helpers = $view->getHelpers();
        if ($isSkeleton) {
            $helpers->set('template', function () use ($templateJs) {
                $result = $this->parseTemplate(true);
                $html = carr::get($result, 'html');
                $js = carr::get($result, 'js');

                return $html . '<script>' . $js . '</script>';
            });
        }

        $helpers->set('content', function () {
            return $this->htmlChild();
        });
        $helpers->set('htmlContent', function () {
            if ($this instanceof CElement) {
                return $this->htmlChild();
            }

            return $this->html();
        });
        $helpers->set('jsContent', function () {
            if ($this instanceof CElement) {
                return $this->jsChild();
            }

            return $this->js();
        });

        $helpers->set('element', function () {
            return $this;
        });
        $helpers->set('section', function ($sectionName) {
            $section = $this->section($sectionName);
            if ($this instanceof CElement) {
                //populate html first, because js sometime populated in html
                $html = $section->htmlChild();
                $this->sectionJs .= $section->jsChild();

                return $html;
            }
            $this->sectionJs .= $section->js();

            return $section->html();
        });

        foreach ($this->helpers as $helperName => $callback) {
            $helpers->set($helperName, $callback);
        }
        $view->set($this->templateData);
        $output = $view->render();

        preg_match_all('#<script>(.*?)</script>#ims', $output, $matches);

        foreach ($matches[1] as $value) {
            $outputJs .= $value;
        }
        $outputHtml = preg_replace('#<script>(.*?)</script>#is', '', $output);

        return [
            'html' => $outputHtml,
            'js' => $outputJs,
        ];
    }

    /**
     * @return bool
     */
    private function collectHtmlJsOnce() {
        if ($this->htmlOutput == null) {
            $this->collectHtmlJs();
        }

        return true;
    }

    /**
     * @return bool
     */
    protected function collectHtmlJs() {
        $obLevel = ob_get_level();
        $resultContent = '';

        try {
            $resultContent = $this->parseTemplate();
        } catch (Exception $ex) {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }

            throw $ex;
        }

        $this->htmlOutput = carr::get($resultContent, 'html', '');
        $this->jsOutput = carr::get($resultContent, 'js', '');

        //htmlChild will concat in helper template content, we need to concat the js here
        $this->jsOutput .= $this->sectionJs;
        if ($this instanceof CElement) {
            $this->jsOutput .= parent::jsChild();
        } elseif ($this instanceof CRenderable) {
            $this->jsOutput .= parent::js();
        }

        return true;
    }

    /**
     * @param callable $callable
     *
     * @return $this
     */
    public function onBeforeParse(callable $callable) {
        $this->onBeforeParse = $callable;

        return $this;
    }

    /**
     * @param int $indent
     *
     * @return string
     */
    public function getTemplateHtml($indent = 0) {
        $this->collectHtmlJsOnce();

        return $this->htmlOutput;
    }

    /**
     * @param int $indent
     *
     * @return string
     */
    public function getTemplateJs($indent = 0) {
        $this->collectHtmlJsOnce();

        return $this->jsOutput;
    }
}
