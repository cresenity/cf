<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since May 3, 2019, 1:46:46 PM
 */
interface CGit_StatisticsInterface {
    public function addCommit(CGit_Model_Commit $commit);

    public function sortCommits();
}
