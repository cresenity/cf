<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * PinCommentBroadcastResponse.
 *
 * @method string getCommentId()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isCommentId()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setCommentId(string $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetCommentId()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class PinCommentBroadcastResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'comment_id' => 'string',
    ];
}
