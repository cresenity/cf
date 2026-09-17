<?php
class CHouseKeeping_FileTemp_AjaxFileTemp {
    /**
     * Execute the housekeeping process to delete old temporary ajax files.
     *
     * @param int $keepDays The number of days to keep temporary ajax files. Files older than this will be deleted.
     *
     * @return bool Returns true if the housekeeping process was successful.
     */
    public static function execute($keepDays = 90) {
        $executed = false;

        $disk = CTemporary::disk();

        $basePath = 'ajax';

        // ajax/<Ymd> (lokasi lama bersama) dan ajax/<appCode>/<Ymd> (per app) dua-duanya dipangkas;
        // nama folder yang bukan Ymd dianggap folder app dan diturunkan satu tingkat.
        $directories = [];
        foreach ($disk->directories($basePath) as $directory) {
            if (strlen(carr::last(explode('/', $directory))) == 8) {
                $directories[] = $directory;
            } else {
                $directories = array_merge($directories, $disk->directories($directory));
            }
        }
        foreach ($directories as $directory) {
            //get last path
            $ymd = carr::last(explode('/', $directory));
            if (strlen($ymd) == 8) {
                //the format maybe is ymd
                //try to parse it to carbon
                $carbonDate = CCarbon::parse($ymd);

                $days = $carbonDate->diffInDays(CCarbon::now());

                if ($days > $keepDays) {
                    if (CDaemon::isDaemon()) {
                        CDaemon::log('deleting folder ' . $directory);
                    }

                    $disk->deleteDirectory($directory);
                    $executed = true;

                    break;
                }
            }
        }

        return $executed;
    }
}
