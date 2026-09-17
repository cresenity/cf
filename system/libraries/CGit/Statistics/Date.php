<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since May 3, 2019, 1:46:04 PM
 */

/**
 * Aggregate statistics based on day.
 */
class CGit_Statistics_Date extends CCollection implements CGit_StatisticsInterface {
    /**
     * @param CGit_Model_Commit $commit
     */
    public function addCommit(CGit_Model_Commit $commit) {
        $day = $commit->getCommiterDate()->format('Y-m-d');
        $this->items[$day][] = $commit;
    }

    public function sortCommits() {
        ksort($this->items);
    }
}
