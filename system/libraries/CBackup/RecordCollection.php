<?php


class CBackup_RecordCollection extends CCollection {

    /** @var null|float */
    protected $sizeCache = null;

    public static function createFromFiles($disk, array $files) {
        return (new static($files))
                        ->filter(function ($path) use ($disk) {
                            $file = new CBackup_File();
                            return $file->isZipFile($disk, $path);
                        })
                        ->map(function ($path) use ($disk) {
                            return new CBackup_Record($disk, $path);
                        })
                        ->sortByDesc(function (CBackup_Record $backup) {
                            return $backup->date()->timestamp;
                        })
                        ->values();
    }

    public function newest() {
        return $this->first();
    }

    public function oldest() {
        return $this->filter->exists()->last();
    }

    public function size() {
        if ($this->sizeCache !== null) {
            return $this->sizeCache;
        }
        return $this->sizeCache = $this->sum->size();
    }

}
