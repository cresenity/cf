<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Feb 16, 2018, 5:40:40 AM
 */

//@codingStandardsIgnoreStart
trait CTrait_Compat_TreeDb {
    /**
     * @param string $filename
     *
     * @return $this
     *
     * @deprecated since version 1.2, please use function setDisplayCallback
     */
    public function set_display_callback() {
        return $this->setDisplayCallback();
    }

    /**
     * @param string $pk_column
     *
     * @return $this
     *
     * @deprecated since version 1.2, please use function setPkColumn
     */
    public function set_pk_column($pk_column) {
        return $this->setPkColumn($pk_column);
    }

    public function set_org_id($id) {
        return $this->setOrgId($id);
    }

    public function add_filter($k, $v) {
        return $this->addFilter($k, $v);
    }

    public function set_delete_child($bool) {
        return $this->setDeleteChild($bool);
    }

    public function set_have_priority($boolean) {
        return $this->setHavePriority($boolean);
    }

    protected function filter_where() {
        return $this->filterWhere();
    }

    public function get_list($indent = '') {
        return $this->getList($indent);
    }

    public function get_parents($parent_id) {
        return $this->getParents($parent_id);
    }

    public function get_first_parent($parent_id) {
        return $this->getFirstParent($parent_id);
    }

    public function get_children_list($id = null, $indent = '') {
        return $this->getChildrenList($id, $indent);
    }

    public function get_children_leaf_list($id = null) {
        return $this->getChildrenLeafList($id);
    }

    public function get_children_data($id = null) {
        return $this->getChildrenData($id);
    }

    public function rebuild_tree_all($force = false) {
        return $this->rebuildTreeAll($force);
    }

    public function rebuild_tree_all_ignore_status() {
        return $this->rebuildTreeAllIgnoreStatus();
    }

    public function rebuild_tree($id = null, $left = 1, $depth = 0) {
        return $this->rebuildTree($id, $left, $depth);
    }

    public function rebuild_tree_ignore_status($id = null, $left = 1, $depth = 0) {
        return $this->rebuildTreeIgnoreStatus($id, $left, $depth);
    }
}
