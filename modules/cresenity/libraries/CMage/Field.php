<?php

class CMage_Field extends CMage_FieldBase {
    protected $name;

    protected $label = '';

    protected $sortable = false;

    protected $width = null;

    protected $controlType = 'text';

    public function __construct($name) {
        $this->setName($name);
    }

    public function getName() {
        return $this->name;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function setLabel($label) {
        $this->label = $label;
        return $this;
    }

    public function getLabel() {
        return $this->label;
    }

    public function setSortable($bool = true) {
        $this->sortable = $bool;
        return $this;
    }

    public function setWidth($width) {
        $this->width = $width;
        return $this;
    }

    /**
     * @param CElement_Component_DataTable $table
     *
     * @return CElement_Component_DataTable_Column
     */
    public function addAsColumn(CElement_Component_DataTable $table) {
        $column = $table->addColumn($this->name)->setLabel($this->label);
        if ($this->width != null) {
            $column->setWidth($this->width);
        }
        $column->setSortable($this->sortable);
        return $column;
    }

    /**
     * @param CElement $container
     *
     * @return CElement_Component_Form_Field
     */
    public function addAsField(CElement $container) {
        $field = $container->addField()->setLabel($this->label);

        return $field;
    }

    /**
     * @param CElement $container
     *
     * @return CElement_FormInput
     */
    public function addAsControl(CElement $container) {
        $control = $container->addControl($this->name, $this->controlType);

        return $control;
    }
}
