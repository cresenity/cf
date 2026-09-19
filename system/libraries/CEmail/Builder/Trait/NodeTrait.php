<?php

trait CEmail_Builder_Trait_NodeTrait {
    /**
     * @param string $tagName
     *
     * @return \CEmail_Builder_Node
     */
    public function addNode($tagName) {
        $node = new CEmail_Builder_Node(['tagName' => $tagName]);
        $this->children[] = $node;

        return $node;
    }

    /**
     * Default atribut untuk semua komponen (c-all), anak dari c-attributes.
     *
     * @return \CEmail_Builder_Node
     */
    public function addAll() {
        return $this->addNode('c-all');
    }

    /**
     * @return CEmail_Builder_Node
     */
    public function addBody() {
        return $this->addNode('c-body');
    }

    /**
     * @return CEmail_Builder_Node
     */
    public function addHead() {
        return $this->addNode('c-head');
    }

    /**
     * @return CEmail_Builder_Node
     */
    public function addHeadAttributes() {
        return $this->addNode('c-attributes');
    }

    /**
     * @return CEmail_Builder_Node
     */
    public function addSection() {
        return $this->addNode('c-section');
    }

    /**
     * @return CEmail_Builder_Node
     */
    public function addColumn() {
        return $this->addNode('c-column');
    }

    /**
     * @return CEmail_Builder_Node
     */
    public function addGroup() {
        return $this->addNode('c-group');
    }

    /**
     * @return CEmail_Builder_Node
     */
    public function addImage() {
        return $this->addNode('c-image');
    }

    /**
     * @return CEmail_Builder_Node
     */
    public function addText() {
        return $this->addNode('c-text');
    }

    /**
     * @return CEmail_Builder_Node
     */
    public function addButton() {
        return $this->addNode('c-button');
    }

    /**
     * @return CEmail_Builder_Node
     */
    public function addDivider() {
        return $this->addNode('c-divider');
    }
}
