<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Feb 16, 2018, 5:40:40 AM
 */
/**
 * @see CObservable
 */
 //@codingStandardsIgnoreStart
trait CTrait_Compat_Observable {
    public function get_listeners() {
        return $this->getListeners();
    }

    /**
     * @param string $event
     *
     * @return CObservable_Listener
     *
     * @deprecated
     */
    public function add_listener($event) {
        return $this->addListener($event);
    }

    /**
     * @param mixed $event
     * @param mixed $listener
     *
     * @deprecated
     */
    public function attach_listener($event, $listener) {
        /** @var CObservable $this */
        return $this->attachListeners($event, $listener);
    }

    /**
     * @param mixed $event
     *
     * @deprecated
     */
    public function detach_listener($event) {
        return $this->detachListener($event);
    }

    public function add_control($id, $type) {
        return $this->addControl($id, $type);
    }

    public function add_div($id = '') {
        return $this->addDiv($id);
    }

    public function add_a($id = '') {
        return $this->addA($id);
    }

    public function add_h1($id = '') {
        return $this->addH1($id);
    }

    public function add_h2($id = '') {
        return $this->addH2($id);
    }

    public function add_h3($id = '') {
        return $this->addH3($id);
    }

    public function add_h4($id = '') {
        return $this->addH4($id);
    }

    public function add_h5($id = '') {
        return $this->addH5($id);
    }

    public function add_h6($id = '') {
        return $this->addH6($id);
    }

    public function add_ol($id = '') {
        return $this->addOl($id);
    }

    public function add_ul($id = '') {
        return $this->addUl($id);
    }

    public function add_li($id = '') {
        return $this->addLi($id);
    }

    public function add_iframe($id = '') {
        return $this->addIframe($id);
    }

    /**
     * @param mixed $field_id
     *
     * @deprecated
     */
    public function add_field($field_id = '') {
        return $this->addField($field_id);
    }

    public function add_fieldset($fieldset_id = '') {
        return $this->addFieldset($fieldset_id);
    }

    /**
     * @param string $tableId
     *
     * @return CElement_Component_DataTable
     *
     * @deprecated since 1.2
     */
    public function add_table($tableId = '') {
        return $this->addTable($tableId);
    }

    public function add_row($row_id = '') {
        return $this->addRow($row_id);
    }

    public function add_calendar($calendar_id = '') {
        return $this->addCalendar($calendar_id);
    }

    /**
     * @param string $tabs_id
     *
     * @return CTabList
     */
    public function add_tab_list($tabs_id = '') {
        return $this->addTabList($tabs_id);
    }

    public function add_tab_static_list($tabs_id = '') {
        return $this->addTabStaticList($tabs_id);
    }

    public function add_elm($tag, $id = '') {
        return $this->addElm($tag, $id);
    }

    public function add_row_fluid($id = '') {
        return $this->addRowFluid($id);
    }

    public function add_img($id = '') {
        return $this->addImg($id);
    }

    public function add_basic_span($id = '') {
        return $this->addBasicSpan($id);
    }

    /**
     * @deprecated Please use addWidget
     *
     * @param string $id
     *
     * @return CElement_Component_Widget
     */
    public function add_widget($id = '') {
        return $this->addWidget($id);
    }

    /**
     * @deprecated Please use addForm
     *
     * @param string $id
     *
     * @return CElement_Component_Form;
     */
    public function add_form($id = '') {
        return $this->addForm($id);
    }

    /**
     * @deprecated Please use addNestable
     *
     * @param string $id
     *
     * @return CElement_Component_Nestable
     */
    public function add_nestable($id = '') {
        return $this->addNestable($id);
    }

    public function add_hr() {
        return $this->addHr();
    }

    public function add_br() {
        return $this->addBr();
    }

    public function add_element($type, $id = '') {
        return $this->addElement($type, $id);
    }

    /**
     * @param string $id
     *
     * @return CElement_List_ActionList
     *
     * @deprecated since 1.2, use addActionList
     */
    public function add_action_list($id = '') {
        /** @var CObservable $this */
        return $this->addActionList($id);
    }

    /**
     * @param string $id
     *
     * @return CElement_Component_Action
     *
     * @deprecated since 1.2, use addAction
     */
    public function add_action($id = '') {
        return $this->addAction($id);
    }

    public function add_pie_chart($id = '') {
        return $this->addPieChart($id);
    }

    /**
     * @deprecated since version 1.2, please use function addDashboard
     *
     * @param mixed $id
     *
     * @return CElement
     */
    public function add_dashboard($id = '') {
        return $this->addDashboard($id);
    }

    /**
     * @deprecated since version 1.2, please use function clearBoth
     *
     * @return $this
     */
    public function clear_both() {
        return $this->clearBoth();
    }

    /**
     * @deprecated since version 1.2, please use function setHandlerUrlParam
     *
     * @param mixed $param
     *
     * @return $this
     */
    public function set_handler_url_param($param) {
        return $this->setHandlerUrlParam($param);
    }

    /**
     * @deprecated since version 1.2, please use function regenerateId
     *
     * @param mixed $recursive
     *
     * @return $this
     */
    public function regenerate_id($recursive = false) {
        return $this->regenerateId($recursive);
    }

    public function add_span($id = '') {
        $span = CSpan::factory($id);
        $this->add($span);

        return $span;
    }
}
//@codingStandardsIgnoreEnd
