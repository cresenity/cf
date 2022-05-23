<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since May 2, 2019, 12:24:32 AM
 */
trait CModel_Resource_Concern_IsSortedTrait {
    public function setHighestOrderNumber() {
        $orderColumnName = $this->determineOrderColumnName();
        $this->$orderColumnName = $this->getHighestOrderNumber() + 1;
    }

    public function getHighestOrderNumber() {
        return (int) static::max($this->determineOrderColumnName());
    }

    public function scopeOrdered(CModel_Query $query) {
        return $query->orderBy($this->determineOrderColumnName());
    }

    /**
     * This function reorders the records: the record with the first id in the array
     * will get order 1, the record with the second it will get order 2, ...
     *
     * A starting order number can be optionally supplied (defaults to 1).
     *
     * @param array $ids
     * @param int   $startOrder
     */
    public static function setNewOrder(array $ids, $startOrder = 1) {
        foreach ($ids as $id) {
            $model = static::find($id);
            $orderColumnName = $model->determineOrderColumnName();
            $model->$orderColumnName = $startOrder++;
            $model->save();
        }
    }

    protected function determineOrderColumnName() {
        return isset($this->sortable['order_column_name']) ? $this->sortable['order_column_name'] : 'order_column';
    }

    public function shouldSortWhenCreating() {
        return isset($this->sortable['sort_when_creating']) ? $this->sortable['sort_when_creating'] : true;
    }
}
