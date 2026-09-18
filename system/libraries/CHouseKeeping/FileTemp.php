<?php

class CHouseKeeping_FileTemp {
    public static function cleanAjaxTemp($keepDays = 90) {
        return CHouseKeeping_FileTemp_AjaxFileTemp::execute($keepDays);
    }

    public static function cleanBackupTemp($keepDays = 90) {
        return CHouseKeeping_FileTemp_BackupFileTemp::execute($keepDays);
    }

    /**
     * Remove image-conversion scratch files left under temp/resource.
     *
     * @param int $keepHours
     *
     * @return bool
     */
    public static function cleanResourceTemp($keepHours = 24) {
        return CHouseKeeping_FileTemp_ResourceFileTemp::execute($keepHours);
    }
}
