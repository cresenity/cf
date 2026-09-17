<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * BroadcastLikeResponse.
 *
 * @method mixed getLikes()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isLikes()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setLikes(mixed $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetLikes()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class BroadcastLikeResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'likes' => '',
    ];
}
