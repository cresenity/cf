<?php

trait CModel_Relation_Trait_SupportsDefaultModels {
    /**
     * Indicates if a default model instance should be used.
     *
     * Alternatively, may be a Closure or array.
     *
     * @var \Closure|array|bool
     */
    protected $withDefault;

    /**
     * Make a new related instance for the given model.
     *
     * @param CModel $parent
     *
     * @return CModel
     */
    abstract protected function newRelatedInstanceFor(CModel $parent);

    /**
     * Return a new model instance in case the relationship does not exist.
     *
     * @param \Closure|array|bool $callback
     *
     * @return $this
     */
    public function withDefault($callback = true) {
        $this->withDefault = $callback;

        return $this;
    }

    /**
     * Get the default value for this relation.
     *
     * @param CModel $parent
     *
     * @return CModel|null
     */
    protected function getDefaultFor(CModel $parent) {
        if (!$this->withDefault) {
            return;
        }

        $instance = $this->newRelatedInstanceFor($parent);

        if (is_callable($this->withDefault)) {
            return call_user_func($this->withDefault, $instance) ?: $instance;
        }

        if (is_array($this->withDefault)) {
            $instance->forceFill($this->withDefault);
        }

        return $instance;
    }
}
