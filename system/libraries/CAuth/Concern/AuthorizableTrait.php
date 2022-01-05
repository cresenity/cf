<?php

trait CAuth_Concern_AuthorizableTrait {
    /**
     * Determine if the entity has the given abilities.
     *
     * @param iterable|string $abilities
     * @param array|mixed     $arguments
     *
     * @return bool
     */
    public function can($abilities, $arguments = []) {
        return CAuth::gate()->forUser($this)->check($abilities, $arguments);
    }

    /**
     * Determine if the entity has any of the given abilities.
     *
     * @param iterable|string $abilities
     * @param array|mixed     $arguments
     *
     * @return bool
     */
    public function canAny($abilities, $arguments = []) {
        return CAuth::gate()->forUser($this)->any($abilities, $arguments);
    }

    /**
     * Determine if the entity does not have the given abilities.
     *
     * @param iterable|string $abilities
     * @param array|mixed     $arguments
     *
     * @return bool
     */
    public function cant($abilities, $arguments = []) {
        return !$this->can($abilities, $arguments);
    }

    /**
     * Determine if the entity does not have the given abilities.
     *
     * @param iterable|string $abilities
     * @param array|mixed     $arguments
     *
     * @return bool
     */
    public function cannot($abilities, $arguments = []) {
        return $this->cant($abilities, $arguments);
    }
}
