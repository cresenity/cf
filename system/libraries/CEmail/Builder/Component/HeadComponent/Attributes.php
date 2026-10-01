<?php

class CEmail_Builder_Component_HeadComponent_Attributes extends CEmail_Builder_Component_HeadComponent {
    protected static $tagName = 'c-attributes';

    public function handler() {
        $childrens = $this->getChildren();
        foreach ($childrens as $child) {
            $children = $child->getChildren();
            $tagName = $child->getTagName();
            $attributes = $child->getAttributes();
            if ($tagName === 'c-class') {
                $this->context->addHead('classes', carr::get($attributes, 'name'), carr::except($attributes, ['name']));
                $classesDefaultParam = carr::reduce($children, function ($acc, $child) {
                    $acc[$child->getTagName()] = $child->getAttributes();

                    return $acc;
                }, []);
                $this->context->addHead('classesDefault', carr::get($attributes, 'name'), $classesDefaultParam);
            } else {
                $this->context->addHead('defaultAttributes', $tagName, $attributes);
            }
        }
    }
}
