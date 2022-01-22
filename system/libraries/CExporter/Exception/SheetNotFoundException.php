<?php

class CExporter_Exception_SheetNotFoundException extends \Exception implements CExporter_ExceptionInterface {
    /**
     * @param string $name
     *
     * @return CExporter_Exception_SheetNotFoundException
     */
    public static function byName($name) {
        return new static("Your requested sheet name [{$name}] is out of bounds.");
    }

    /**
     * @param int $index
     * @param int $sheetCount
     *
     * @return CExporter_Exception_SheetNotFoundException
     */
    public static function byIndex($index, $sheetCount) {
        return new static("Your requested sheet index: {$index} is out of bounds. The actual number of sheets is {$sheetCount}.");
    }
}
