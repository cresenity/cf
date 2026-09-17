<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Oct 1, 2019, 3:11:35 PM
 */
interface CExcel_ToModelInterface {
    /**
     * @param array $row
     *
     * @return null|CModel|CModel[]
     */
    public function model(array $row);
}
