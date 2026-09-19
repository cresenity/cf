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
     * @param array $options
     *
     * @return string
     */
    public function render(array $options = []) {
        $parser = new CEmail_Builder_Parser($this->node, $options);

        return $parser->parse();
    }
}
