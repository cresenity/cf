<?php

class CModel_MongoDB_Relation_HasOne extends CModel_Relation_HasOne {
    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getForeignKeyName() {
        return $this->foreignKey;
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getHasCompareKey() {
        return $this->getForeignKeyName();
    }

    /**
     * Get the plain foreign key.
     *
     * @return string
     */
    public function getPlainForeignKey() {
        return $this->getForeignKeyName();
    }

    /**
     * @inheritdoc
     */
    public function getRelationExistenceQuery(CModel_Query $query, CModel_Query $parentQuery, $columns = ['*']) {
        $foreignKey = $this->getForeignKeyName();

        return $query->select($foreignKey)->where($foreignKey, 'exists', true);
    }

    /**
     * Add the constraints for a relationship count query.
     *
     * @param CModel_Query $query
     * @param CModel_Query $parent
     *
     * @return CModel_Query
     */
    public function getRelationCountQuery(CModel_Query $query, CModel_Query $parent) {
        $foreignKey = $this->getForeignKeyName();

        return $query->select($foreignKey)->where($foreignKey, 'exists', true);
    }

    /**
     * Get the name of the "where in" method for eager loading.
     *
     * @param CModel $model
     * @param string $key
     *
     * @return string
     */
    protected function whereInMethod(CModel $model, $key) {
        return 'whereIn';
    }
}
