<?php

/**
 * Description of DatabaseCheckerTrait
 */
trait CQC_Manager_DatabaseCheckerTrait {
    protected $databaseChecker = [];

    protected $databaseCheckerGroup = [];

    /**
     * @param string $class
     * @param string $name
     * @param string $group
     */
    public function registerDatabaseChecker($class, $name = null, $group = null) {
        if ($name == null) {
            $name = carr::last(explode('_', $class));
        }
        $this->databaseChecker[$class] = $name;
        if ($group !== null) {
            if (!isset($this->databaseCheckerGroup[$group])) {
                $this->databaseCheckerGroup[$group] = [];
            }
            $this->databaseCheckerGroup[$group][$class] = $name;
        }
    }

    /**
     * @param string $group
     *
     * @return array
     *
     * @throws Exception
     */
    public function databaseCheckers($group = null) {
        if ($group === null) {
            return $this->databaseChecker;
        }
        if ($group === false) {
            $allDaemons = $this->databaseChecker;
            foreach ($this->databaseCheckerGroup as $groupArray) {
                $allDaemons = array_diff_key($allDaemons, $groupArray);
            }
            return $allDaemons;
        }
        if ($group !== null) {
            if (!in_array($group, $this->getDatabaseCheckerGroupsKey())) {
                throw new Exception('group daemon ' . $group . ' not available');
            }
        }
        return $this->databaseCheckerGroup[$group];
    }

    /**
     * @return array
     */
    public function getDatabaseCheckerGroupsKey() {
        return array_keys($this->databaseCheckerGroup);
    }

    /**
     * @return bool
     */
    public function haveDatabaseCheckerGroup() {
        return count($this->getDatabaseCheckerGroupsKey()) > 0;
    }
}
