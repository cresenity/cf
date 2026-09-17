<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * EnableDisableLiveCommentsResponse.
 *
 * @method int getCommentMuted()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isCommentMuted()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setCommentMuted(int $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetCommentMuted()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class EnableDisableLiveCommentsResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'comment_muted' => 'int',
    ];
}
