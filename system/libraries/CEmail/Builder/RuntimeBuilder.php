<?php

/**
 * @mixed CEmail_Builder_Node
 *
 * @method CEmail_Builder_Node addBody()
 * @method CEmail_Builder_Node addHead()
 * @method CEmail_Builder_Node addHeadAttributes()
 * @method CEmail_Builder_Node addSection()
 * @method CEmail_Builder_Node addColumn()
 * @method CEmail_Builder_Node addGroup()
 * @method CEmail_Builder_Node addImage()
 * @method CEmail_Builder_Node addText()
 * @method CEmail_Builder_Node addDivider()
 * @method CEmail_Builder_Node addButton()
 * @method CEmail_Builder_Node addSpacer()
 * @method CEmail_Builder_Node addWrapper()
 * @method CEmail_Builder_Node addRaw()
 * @method CEmail_Builder_Node addSocial()
 * @method CEmail_Builder_Node addSocialElement()
 * @method CEmail_Builder_Node addStyle($css, $inline = false)
 * @method CEmail_Builder_Node addClass($name)
 * @method CEmail_Builder_Node addTitle($text)
 * @method CEmail_Builder_Node addPreview($text)
 * @method CEmail_Builder_Node addFont($name, $href)
 * @method CEmail_Builder_Node addBreakpoint($width)
 */
class CEmail_Builder_RuntimeBuilder {
    /**
     * @var CEmail_Builder_Node
     */
    protected $node;

    public function __construct() {
        $this->node = new CEmail_Builder_Node(['tagName' => 'cml']);
    }

    public function __call($method, $args) {
        if (method_exists($this->node, $method) || cstr::startsWith($method, 'set')) {
            return call_user_func_array([$this->node, $method], $args);
        }

        throw new Exception('not defined method ' . $method);
    }

    /**
     * @param array $options fonts (nama => url, [] tanpa link font), validationLevel (soft|strict|skip), keepComments
     *
     * @return string
     */
    public function render(array $options = []) {
        $parser = new CEmail_Builder_Parser($this->node, $options);

        return $parser->parse();
    }
}
