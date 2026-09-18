<?php

/**
 * Description of ToModel
 */
interface CExporter_Concern_ToModel {

    /**
     * @param array $row
     *
     * @return CModel|CModel[]|null
     */
    public function model(array $row);
}
