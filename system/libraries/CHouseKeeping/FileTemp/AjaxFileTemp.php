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
            if (static::isYmd(carr::last(explode('/', $directory)))) {
                $directories[] = $directory;
            } else {
                $directories = array_merge($directories, $disk->directories($directory));
            }
        }
        foreach ($directories as $directory) {
            //get last path
            $ymd = carr::last(explode('/', $directory));
            if (static::isYmd($ymd)) {
                try {
                    $carbonDate = CCarbon::createFromFormat('Ymd', $ymd)->startOfDay();
                } catch (Exception $e) {
                    continue;
                }
                if ($carbonDate->format('Ymd') !== $ymd) {
                    continue;
                }

                $days = $carbonDate->diffInDays(CCarbon::now());

                if ($days > $keepDays) {
                    if (CDaemon::isDaemon()) {
                        CDaemon::log('deleting folder ' . $directory);
                    }
                    if (CCron::isCron()) {
                        CCron::log('deleting folder ' . $directory);
                    }

                    $disk->deleteDirectory($directory);
                    $executed = true;

                    break;
                }
            }
        }

        return $executed;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    protected static function isYmd($name) {
        return preg_match('/^\d{8}$/', (string) $name) === 1;
    }
}
