<?php

/**
 * Description of SkipsOnError
 */
interface CExporter_Concern_SkipsOnError {

    /**
     * @param Exception $e
     */
    public function onError(Exception $e);
}
