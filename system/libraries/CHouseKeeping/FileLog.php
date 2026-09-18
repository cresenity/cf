<?php

class CHouseKeeping_FileLog {
    public static function cleanAppLog($keepDays = 90) {
        return CHouseKeeping_FileLog_AppLog::execute($keepDays);
    }
}
