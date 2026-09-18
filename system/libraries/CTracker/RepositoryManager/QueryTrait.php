<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 9:47:33 PM
 */
trait CTracker_RepositoryManager_QueryTrait {
    /**
     * @var CTracker_Repository_Query
     */
    protected $queryRepository;

    /**
     * @var CTracker_Repository_QueryArgument
     */
    protected $queryArgumentRepository;

    protected function bootQueryTrait() {
        $this->queryRepository = new CTracker_Repository_Query();
        $this->queryArgumentRepository = new CTracker_Repository_QueryArgument();
    }

    public function getQueryId($query) {
        if (!$query) {
            return;
        }
        return $this->findOrCreateQuery($query);
    }

    public function findOrCreateQuery($data) {
        $id = $this->queryRepository->findOrCreate($data, ['query'], $created);
        if ($created) {
            foreach ($data['arguments'] as $argument => $value) {
                if (is_array($value)) {
                    $value = carr::implodes(',', $value);
                }
                $this->queryArgumentRepository->create(
                    [
                        'log_query_id' => $id,
                        'argument' => $argument,
                        'value' => empty($value) ? '' : $value,
                    ]
                );
            }
        }
        return $id;
    }
}
