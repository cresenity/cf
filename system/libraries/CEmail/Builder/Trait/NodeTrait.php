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

    /**
     * Blok HTML mentah; isi lewat add('<html>').
     *
     * @return \CEmail_Builder_Node
     */
    public function addRaw() {
        return $this->addNode('c-raw');
    }

    /**
     * @return \CEmail_Builder_Node
     */
    public function addSocial() {
        return $this->addNode('c-social');
    }

    /**
     * @return \CEmail_Builder_Node
     */
    public function addSocialElement() {
        return $this->addNode('c-social-element');
    }

    /**
     * @return \CEmail_Builder_Node
     */
    public function addSpacer() {
        return $this->addNode('c-spacer');
    }

    /**
     * @return \CEmail_Builder_Node
     */
    public function addWrapper() {
        return $this->addNode('c-wrapper');
    }

    /**
     * CSS di head (anak dari addHead()).
     *
     * @param string $css
     * @param bool   $inline
     *
     * @return \CEmail_Builder_Node
     */
    public function addStyle($css, $inline = false) {
        $node = $this->addNode('c-style');
        if ($inline) {
            $node->setAttr('inline', 'inline');
        }

        return $node->add((string) $css);
    }

    /**
     * Kelas atribut bernama (anak dari addHeadAttributes()); pakai lewat useClass() di node lain.
     *
     * @param string $name
     *
     * @return \CEmail_Builder_Node
     */
    public function addClass($name) {
        return $this->addNode('c-class')->setAttr('name', $name);
    }

    /**
     * @param string $text
     *
     * @return \CEmail_Builder_Node
     */
    public function addTitle($text) {
        return $this->addNode('c-title')->add((string) $text);
    }

    /**
     * @param string $text
     *
     * @return \CEmail_Builder_Node
     */
    public function addPreview($text) {
        return $this->addNode('c-preview')->add((string) $text);
    }

    /**
     * @param string $name
     * @param string $href
     *
     * @return \CEmail_Builder_Node
     */
    public function addFont($name, $href) {
        return $this->addNode('c-font')->setAttr('name', $name)->setAttr('href', $href);
    }

    /**
     * @param string $width contoh 480px
     *
     * @return \CEmail_Builder_Node
     */
    public function addBreakpoint($width) {
        return $this->addNode('c-breakpoint')->setAttr('width', $width);
    }
}
