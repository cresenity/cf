<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * StartLiveResponse.
 *
 * @method string getMediaId()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMediaId()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setMediaId(string $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMediaId()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class StartLiveResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'media_id' => 'string',
    ];
}
