<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * SegmentedStartResponse.
 *
 * @method mixed getMessage()
 * @method string getStatus()
 * @method string getStreamId()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isStreamId()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setStreamId(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetStreamId()
 * @method $this unsetZMessages()
 */
class SegmentedStartResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'stream_id' => 'string',
    ];

    /**
     * Checks if the response was successful.
     *
     * @return bool
     */
    public function isOk()
    {
        $streamId = $this->_getProperty('stream_id');
        if ($streamId !== null && $streamId !== '') {
            return true;
        } else {
            // Set a nice message for exceptions.
            if ($this->getMessage() === null) {
                $this->setMessage('Stream ID for segmented uploader is missing or invalid.');
            }

            return false;
        }
    }
}
