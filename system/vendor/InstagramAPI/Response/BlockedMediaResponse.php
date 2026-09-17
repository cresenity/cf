<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * BlockedMediaResponse.
 *
 * @method mixed getMediaIds()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMediaIds()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setMediaIds(mixed $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMediaIds()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class BlockedMediaResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'media_ids' => '',
    ];
}
