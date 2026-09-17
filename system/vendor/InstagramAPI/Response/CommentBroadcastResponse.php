<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * CommentBroadcastResponse.
 *
 * @method Model\Comment getComment()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isComment()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setComment(Model\Comment $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetComment()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class CommentBroadcastResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'comment' => 'Model\Comment',
    ];
}
