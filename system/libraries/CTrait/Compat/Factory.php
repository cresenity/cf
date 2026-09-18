<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Feb 17, 2018, 4:18:47 AM
 */
 //@codingStandardsIgnoreStart
trait CTrait_Compat_Factory {
    /**
     * @param string $id
     * @param string $type
     *
     * @deprecated since version 1.2, please use createControl
     *
     * @return CElement_FormInput
     */
    public static function create_control($id, $type) {
        return self::createControl($id, $type);
    }

    /**
     * @param string $fieldId
     *
     * @deprecated since version 1.2, please use createField
     *
     * @return CElement_Component_Form_Field
     */
    public static function create_field($fieldId = '') {
        return self::createField($fieldId);
    }

    /**
     * @param string $tableId
     *
     * @deprecated since version 1.2, please use createTable
     *
     * @return CElement_Component_DataTable
     */
    public static function create_table($tableId = '') {
        return self::createTable($tableId);
    }

    /**
     * @param string $id
     *
     * @deprecated since version 1.2, please use createNestable
     *
     * @return CElement_Component_Nestable
     */
    public static function create_nestable($id = '') {
        return static::createNestable($id);
    }

    /**
     * @param string $id
     *
     * @deprecated since version 1.2, please use createTableRow
     *
     * @return CElement_Component_TableRow
     */
    public static function create_row($id = '') {
        return static::createTableRow($id);
    }

    /**
     * @param string $id
     *
     * @deprecated since version 1.7, please use createDiv
     *
     * @return CElement_Element_Div
     */
    public static function create_div($id = '') {
        return self::createDiv($id);
    }

    /**
     * @param string $id
     *
     * @deprecated since version 1.7, please use createAction
     *
     * @return CElement_Component_Action
     */
    public static function create_action($id = '') {
        return self::createAction($id);
    }
}
