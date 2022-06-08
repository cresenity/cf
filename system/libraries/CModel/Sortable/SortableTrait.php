<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Mar 31, 2019, 6:18:55 PM
 *
 * @method CModel_Query|static ordered()
 */
trait CModel_Sortable_SortableTrait {
    public static function bootSortableTrait() {
        static::creating(function ($model) {
            $traitUses = c::traitUsesRecursive($model);
            if (in_array(CModel_Sortable_SortableTrait::class, $traitUses) && $model->shouldSortWhenCreating()) {
                $model->setHighestOrderNumber();
            }
        });
    }

    /**
     * Modify the order column value.
     */
    public function setHighestOrderNumber() {
        $orderColumnName = $this->determineOrderColumnName();
        $this->$orderColumnName = $this->getHighestOrderNumber() + 1;
    }

    /**
     * Determine the order value for the new record.
     */
    public function getHighestOrderNumber() {
        return (int) $this->buildSortQuery()->max($this->determineOrderColumnName());
    }

    /**
     * Let's be nice and provide an ordered scope.
     *
     * @param CModel_Query $query
     * @param string       $direction
     *
     * @return CDatabase_Query_Builder
     */
    public function scopeOrdered(CModel_Query $query, $direction = 'asc') {
        return $query->orderBy($this->determineOrderColumnName(), $direction);
    }

    /**
     * This function reorders the records: the record with the first id in the array
     * will get order 1, the record with the second it will get order 2, ...
     *
     * A starting order number can be optionally supplied (defaults to 1).
     *
     * @param array|\ArrayAccess $ids
     * @param int                $startOrder
     */
    public static function setNewOrder($ids, $startOrder = 1) {
        if (!is_array($ids) && !$ids instanceof ArrayAccess) {
            throw new InvalidArgumentException('You must pass an array or ArrayAccess object to setNewOrder');
        }
        $model = new static();
        $orderColumnName = $model->determineOrderColumnName();
        $primaryKeyColumn = $model->getKeyName();
        foreach ($ids as $id) {
            static::withoutGlobalScope(CModel_SoftDelete_Scope::class)
                ->where($primaryKeyColumn, $id)
                ->update([$orderColumnName => $startOrder++]);
        }
    }

    /**
     * Determine the column name of the order column.
     */
    protected function determineOrderColumnName() {
        if (isset($this->sortable['order_column_name'])
            && !empty($this->sortable['order_column_name'])
        ) {
            return $this->sortable['order_column_name'];
        }

        return 'order_column';
    }

    /**
     * Determine if the order column should be set when saving a new model instance.
     */
    public function shouldSortWhenCreating() {
        return isset($this->sortable['sort_when_creating']) ? $this->sortable['sort_when_creating'] : true;
    }

    /**
     * Swaps the order of this model with the model 'below' this model.
     *
     * @return $this
     */
    public function moveOrderDown() {
        $orderColumnName = $this->determineOrderColumnName();
        $swapWithModel = $this->buildSortQuery()->limit(1)
            ->ordered()
            ->where($orderColumnName, '>', $this->$orderColumnName)
            ->first();
        if (!$swapWithModel) {
            return $this;
        }

        return $this->swapOrderWithModel($swapWithModel);
    }

    /**
     * Swaps the order of this model with the model 'above' this model.
     *
     * @return $this
     */
    public function moveOrderUp() {
        $orderColumnName = $this->determineOrderColumnName();
        $swapWithModel = $this->buildSortQuery()->limit(1)
            ->ordered('desc')
            ->where($orderColumnName, '<', $this->$orderColumnName)
            ->first();
        if (!$swapWithModel) {
            return $this;
        }

        return $this->swapOrderWithModel($swapWithModel);
    }

    /**
     * Swap the order of this model with the order of another model.
     *
     * @param CModel_Sortable_SortableInterface $otherModel
     *
     * @return $this
     */
    public function swapOrderWithModel(CModel_Sortable_SortableInterface $otherModel) {
        /** @var $this $otherModel * */
        $orderColumnName = $this->determineOrderColumnName();
        $oldOrderOfOtherModel = $otherModel->$orderColumnName;
        $otherModel->$orderColumnName = $this->$orderColumnName;
        $otherModel->save();
        $this->$orderColumnName = $oldOrderOfOtherModel;
        $this->save();

        return $this;
    }

    /**
     * Swap the order of two models.
     *
     * @param CModel_Sortable_SortableInterface $model
     * @param CModel_Sortable_SortableInterface $otherModel
     */
    public static function swapOrder(CModel_Sortable_SortableInterface $model, CModel_Sortable_SortableInterface $otherModel) {
        /** @var $this $model * */
        $model->swapOrderWithModel($otherModel);
    }

    /**
     * Moves this model to the first position.
     *
     * @return $this
     */
    public function moveToStart() {
        $firstModel = $this->buildSortQuery()->limit(1)
            ->ordered()
            ->first();
        if ($firstModel->id === $this->id) {
            return $this;
        }
        $orderColumnName = $this->determineOrderColumnName();
        $this->$orderColumnName = $firstModel->$orderColumnName;
        $this->save();
        $this->buildSortQuery()->where($this->getKeyName(), '!=', $this->id)->increment($orderColumnName);

        return $this;
    }

    /**
     * Moves this model to the last position.
     *
     * @return $this
     */
    public function moveToEnd() {
        $maxOrder = $this->getHighestOrderNumber();
        $orderColumnName = $this->determineOrderColumnName();
        if ($this->$orderColumnName === $maxOrder) {
            return $this;
        }
        $oldOrder = $this->$orderColumnName;
        $this->$orderColumnName = $maxOrder;
        $this->save();
        $this->buildSortQuery()->where($this->getKeyName(), '!=', $this->id)
            ->where($orderColumnName, '>', $oldOrder)
            ->decrement($orderColumnName);

        return $this;
    }

    /**
     * Build eloquent builder of sortable.
     *
     * @return \CModel_Query
     */
    public function buildSortQuery() {
        return static::query();
    }
}
