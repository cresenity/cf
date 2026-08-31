<?php

/**
 * Show the local DevCloud login session: token path, expiry, and a live
 * check of whether it's actually usable right now.
 */
class CDevSuite_Command_DevCloud_SessionCommand extends CDevSuite_CommandAbstract {
    /**
     * @param CConsole_Command $cfCommand
     *
     * @return int|void
     */
    public function run(CConsole_Command $cfCommand) {
        $status = CDevSuite::devCloudApi()->session();

        if (!$status['logged_in']) {
            CDevSuite::error('Not logged in to DevCloud, run `phpcf devcloud:login` first.');
            $cfCommand->line('Token path: ' . $status['token_path']);

            return CConsole::FAILURE_EXIT;
        }

        $cfCommand->table(['Field', 'Value'], [
            ['Token path', $status['token_path']],
            ['File last written', static::formatTimestamp($status['file_modified_at'])],
            ['Access token expires', static::formatTimestamp($status['access_token_expires_at'])],
            ['Access token expired (local clock)', $status['access_token_expired'] ? 'yes' : 'no'],
            ['Refresh token cached', $status['has_refresh_token'] ? 'yes' : 'no'],
            ['Usable right now (live check)', $status['live_valid'] ? 'yes' : 'no'],
        ]);

        if (!$status['live_valid']) {
            CDevSuite::error($status['live_error'] ?: 'Cached credentials are not usable right now.');
            $cfCommand->line('Run `phpcf devcloud:login` to get a fresh session.');

            return CConsole::FAILURE_EXIT;
        }

        CDevSuite::success('DevCloud session is valid.');
    }

    /**
     * @param null|int $timestamp
     *
     * @return string
     */
    protected static function formatTimestamp($timestamp) {
        if (empty($timestamp)) {
            return '-';
        }

        $date = CCarbon::createFromTimestamp($timestamp);

        return $date->toDateTimeString() . ' (' . $date->diffForHumans() . ')';
    }
}
