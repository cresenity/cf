<?php

class CVendor_Sentry {
    /**
     * @param string $dsn format DSN Sentry, mis. 'https://<public_key>@<host>/<project_id>'
     *
     * @return CVendor_Sentry_Client
     */
    public static function client($dsn) {
        return new CVendor_Sentry_Client($dsn);
    }
}
