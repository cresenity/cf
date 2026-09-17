<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * ResumableOffsetResponse.
 *
 * @method mixed getMessage()
 * @method int getOffset()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isOffset()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setOffset(int $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetOffset()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class ResumableOffsetResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'offset' => 'int',
    ];

    /**
     * Checks if the response was successful.
     *
     * @return bool
     */
    public function isOk()
    {
        $offset = $this->_getProperty('offset');
        if ($offset !== null && $offset >= 0) {
            return true;
        } else {
            // Set a nice message for exceptions.
            if ($this->getMessage() === null) {
                $this->setMessage('Offset for resumable uploader is missing or invalid.');
            }

            return false;
        }
    }
}
