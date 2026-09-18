<?php
class CHouseKeeping_FileLog_AppLog {
    /**
     * Execute the housekeeping process to delete old per-app log folders.
     *
     * Targets `<DOCROOT>logs/<appCode>/<year>/<month>/` - the layout
     * `CLogger_Manager::configurationFor()` builds for a `single`/`daily`
     * channel with no explicit `path` - and deletes one stale month folder
     * per call, same pattern as {@see CHouseKeeping_FileTemp_AjaxFileTemp}.
     *
     * @param int $keepDays The number of days to keep app log folders. A month folder whose last day is older than this is deleted.
     *
     * @return bool Returns true if a folder was deleted.
     */
    public static function execute($keepDays = 90) {
        $executed = false;

        $basePath = DOCROOT . 'logs' . DS . CF::appCode();
        if (!CFile::isDirectory($basePath)) {
            return false;
        }

        foreach (CFile::directories($basePath) as $yearDir) {
            $year = carr::last(explode(DS, rtrim($yearDir, DS)));
            if (!ctype_digit($year) || cstr::length($year) != 4) {
                continue;
            }

            foreach (CFile::directories($yearDir) as $monthDir) {
                $month = carr::last(explode(DS, rtrim($monthDir, DS)));
                if (!ctype_digit($month)) {
                    continue;
                }

                $folderDate = CCarbon::createFromDate((int) $year, (int) $month, 1)->endOfMonth();
                $days = $folderDate->diffInDays(CCarbon::now());

                if ($folderDate->isPast() && $days > $keepDays) {
                    if (CDaemon::isDaemon()) {
                        CDaemon::log('deleting folder ' . $monthDir);
                    }

                    CFile::deleteDirectory($monthDir);
                    $executed = true;

                    break 2;
                }
            }
        }

        return $executed;
    }
}
