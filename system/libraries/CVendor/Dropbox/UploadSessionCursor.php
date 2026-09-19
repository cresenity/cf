<?php


class CVendor_Dropbox_UploadSessionCursor {
    public $sessionId;

    public $offset;

    /**
     * Create a new upload session cursor.
     *
     * @param string $session_id the upload session ID (returned by upload_session/start)
     * @param int    $offset     The amount of data that has been uploaded so far. We use this to make sure upload data isn't lost or duplicated in the event of a network error.
     */
    public function __construct(
        string $sessionId,
        int $offset = 0
    ) {
        $this->sessionId = $sessionId;
        $this->offset = $offset;
    }
}
