<?php

use CElement_Component_Blockly_Helper as Helper;

class CElement_Component_Blockly extends CElement_Component {
    /**
     * @var string
     */
    protected $mediaDirectory;

    /**
     * @var CElement_Component_Blockly_Toolbox
     */
    protected $toolbox;

    /**
     * @var CElement_List_ActionList
     */
    protected $toolbar;

    /**
     * @var CElement_Element_Div
     */
    protected $blocklyWrapper;

    /**
     * @var CElement_Component_Action
     */
    protected $saveAction;

    /**
     * @var array
     */
    protected $variables;

    /**
     * @var bool
     */
    protected $isFunctionWithReturn = false;

    /**
     * @var string
     */
    protected $functionName = '';

    /**
     * @var array
     */
    protected $functionArgs = [];

    /**
     * @var string
     */
    protected $saveUrl = '';

    /**
     * @param string $id
     * @param string $tag
     *
     * @return void
     */
    public function __construct($id = '', $tag = 'div') {
        parent::__construct($id, $tag);
        if (!CManager::isRegisteredModule('blockly')) {
            CManager::registerModule('blockly');
        }
        $this->tag = 'div';
        $this->toolbox = new CElement_Component_Blockly_Toolbox();
        $this->toolbar = new CElement_List_ActionList();
        $this->blocklyWrapper = new CElement_Element_Div();

        $this->addClass('capp-blockly');
        $this->blocklyWrapper->customCss('height', '600px');
        $this->add($this->toolbar);
        $this->blocklyWrapper->add($this->toolbox);
        $this->add($this->blocklyWrapper);
        $this->saveAction = $this->toolbar->addAction()->setLabel('Save');
        $this->variables = [];
    }

    /**
     * @param string $variable
     *
     * @return $this
     */
    public function addVariable($variable) {
        $this->variables[] = $variable;
        return $this;
    }

    /**
     * @param string $funcName
     * @param array  $arguments
     *
     * @return void
     */
    public function setFunctionWithReturn($funcName, $arguments = []) {
        $this->isFunctionWithReturn = true;
        $this->functionName = $funcName;
        $this->functionArgs = $arguments;
    }

    /**
     * @param string $url
     *
     * @return $this
     */
    public function setSaveUrl($url) {
        $this->saveUrl = $url;
        return $this;
    }

    /**
     * @return void
     */
    public function build() {
    }

    /**
     * @param int $indent
     *
     * @return string
     */
    public function js($indent = 0) {
        $toolboxId = $jsOptions = [];
        $jsOptions['blocklyElementId'] = $this->blocklyWrapper->id();
        $jsOptions['toolboxElementId'] = $this->toolbox->id();
        $jsOptions['saveElementId'] = $this->saveAction->id();
        $jsOptions['mediaFolder'] = curl::base() . 'system/media/js/blockly/media/';
        $jsOptions['variables'] = $this->variables;
        $jsOptions['saveUrl'] = $this->saveUrl;
        if ($this->isFunctionWithReturn) {
            $jsOptions['defaultXml'] = Helper::buildDefaultXmlForFunction($this->functionName, $this->functionArgs);
        }
        return '
            new CBlockly(' . json_encode($jsOptions) . ');
        ';
    }
}
