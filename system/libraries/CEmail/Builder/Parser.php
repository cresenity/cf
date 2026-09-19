<?php

class CEmail_Builder_Parser {
    /**
     * @var CEmail_Builder_Node
     */
    protected $node;

    /**
     * @var array
     */
    protected $globalData = [];

    protected $errors = [];

    protected $content = '';

    protected $context = null;

    public function __construct($cml, $options = []) {
        $this->content = '';
        $this->errors = [];
        $globalData = CEmail::Builder()->globalData();
        $globalData->reset();

        $defaultFonts = [];
        $defaultFonts['Open Sans'] = 'https://fonts.googleapis.com/css?family=Open+Sans:300,400,500,700';
        $defaultFonts['Droid Sans'] = 'https://fonts.googleapis.com/css?family=Droid+Sans:300,400,500,700';
        $defaultFonts['Lato'] = 'https://fonts.googleapis.com/css?family=Lato:300,400,500,700';
        $defaultFonts['Roboto'] = 'https://fonts.googleapis.com/css?family=Roboto:300,400,500,700';
        $defaultFonts['Ubuntu'] = 'https://fonts.googleapis.com/css?family=Ubuntu:300,400,500,700';

        $this->node = $cml;
        $beautify = carr::get($options, 'beautify', false);
        $fonts = carr::get($options, 'fonts', $defaultFonts);
        $keepComments = carr::get($options, 'keepComments', false);
        $minify = carr::get($options, 'minify', false);
        $minifyOptions = carr::get($options, 'minifyOptions', []);
        $validationLevel = carr::get($options, 'validationLevel', 'soft');
        $filePath = carr::get($options, 'filePath', '.');

        if (is_string($this->node)) {
            $parserOptions = [];
            $parserOptions['keepComments'] = $keepComments;
            $parserOptions['components'] = CEmail::builder()->components();
            $parserOptions['filePath'] = $filePath;

            $cmlParser = new CEmail_Builder_Parser_CmlParser($this->node, $parserOptions);
            $this->node = $cmlParser->parse();
        }

        $globalData->set('backgroundColor', '');
        $globalData->set('breakpoint', '480px');
        $globalData->set('classes', []);
        $globalData->set('classesDefault', []);
        $globalData->set('defaultAttributes', []);
        $globalData->set('fonts', $fonts);
        $globalData->set('inlineStyle', []);
        $globalData->set('headStyle', []);
        $globalData->set('componentHeadStyle', []);
        $globalData->set('headRaw', []);
        $globalData->set('mediaQueries', []);
        $globalData->set('preview', '');
        $globalData->set('style', []);
        $globalData->set('title', '');
        $globalData->set('forceOWADesktop', c::get($this->node, 'attributes.owa', 'mobile') === 'desktop');
        $globalData->set('lang', c::get($this->node, 'attributes.lang'));

        $this->context = new CEmail_Builder_Context();
    }

    /**
     * @return string
     */
    public function parse() {
        //$this->globalDatas['headRaw'] = $this->processing($cHead, $headHelpers);
        CEmail::Builder()->globalData()->set('headRaw', $this->getHead());
        $content = $this->getContent();
        $options = $this->globalData;

        $renderer = new CEmail_Builder_Renderer($content, $options);

        return trim($renderer->render());
    }

    protected function getHead() {
        if ($this->node == null) {
            return null;
        }
        $cHead = carr::find($this->node->children, ['tagName' => 'c-head']);
        if ($cHead != null) {
            $name = $cHead->getComponentName();

            $options = [];
            $options['attributes'] = $cHead->getAttributes();
            $options['children'] = $cHead->getChildren();
            $options['name'] = $name;
            $options['context'] = $this->context;
            $options['content'] = $cHead->getContent();
            //$options = $callbackParseCML();
            //$options['context'] = $context;
            $component = CEmail::builder()->createComponent($name, $options);

            return $component->handler();
        }

        return [];
    }

    protected function getContent() {
        if ($this->node == null) {
            return null;
        }
        $node = $this->node;
        $cBody = carr::find($this->node->children, ['tagName' => 'c-body']);
        if ($cBody == null) {
            throw new CEmail_Builder_Exception('Email builder membutuhkan satu c-body (panggil addBody() lebih dulu)');
        }

        $name = $cBody->getComponentName();

        $options = [];
        $options['attributes'] = $cBody->getAttributes();
        $options['children'] = $cBody->getChildren();
        $options['name'] = $name;
        $options['context'] = $this->context;
        $options['content'] = $cBody->getContent();
        //$options = $callbackParseCML();
        //$options['context'] = $context;
        $component = CEmail::builder()->createComponent($name, $options);

        return $component->render();
    }

    public function processing($node, $context, $callbackParseCML = null) {
        $name = $node->tagName;
        if (cstr::startsWith($name, 'c-')) {
            $name = substr($name, '2');
        }

        if ($callbackParseCML == null) {
            $callbackParseCML = ['c', 'identity'];
        }

        $options = $callbackParseCML();
        $options['context'] = $context;

        //$component = CEmail::builder()->initComponent($name, $options);
    }
}
