<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Nov 6, 2019, 8:34:10 PM
 */
class CModel_MongoDB_Relation_HasMany extends CModel_Relation_HasMany {
    /**
     * Get the plain foreign key.
     *
     * @return string
     */
    public function getForeignKeyName() {
        return $this->foreignKey;
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
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getHasCompareKey() {
        return $this->getForeignKeyName();
    }

    /**
     * @inheritdoc
     */
    public function getRelationExistenceQuery(CModel_Query $query, CModel_Query $parentQuery, $columns = ['*']) {
        $foreignKey = $this->getHasCompareKey();
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
        $foreignKey = $this->getHasCompareKey();
        return $query->select($foreignKey)->where($foreignKey, 'exists', true);
    }

    /**
     * Add the constraints for a relationship query.
     *
     * @param CModel_Query $query
     * @param CModel_Query $parent
     * @param array|mixed  $columns
     *
     * @return CModel_Query
     */
    public function getRelationQuery(CModel_Query $query, CModel_Query $parent, $columns = ['*']) {
        $query->select($columns);
        $key = $this->wrap($this->getQualifiedParentKeyName());
        return $query->where($this->getHasCompareKey(), 'exists', true);
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
