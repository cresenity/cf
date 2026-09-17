<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * EditMediaResponse.
 *
 * @method Model\Item getMedia()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMedia()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setMedia(Model\Item $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMedia()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class EditMediaResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'media' => 'Model\Item',
    ];
}
