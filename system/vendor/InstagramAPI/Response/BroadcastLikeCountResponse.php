<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * BroadcastLikeCountResponse.
 *
 * @method int getBurstLikes()
 * @method int getLikeTs()
 * @method Model\User[] getLikers()
 * @method int getLikes()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isBurstLikes()
 * @method bool isLikeTs()
 * @method bool isLikers()
 * @method bool isLikes()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setBurstLikes(int $value)
 * @method $this setLikeTs(int $value)
 * @method $this setLikers(Model\User[] $value)
 * @method $this setLikes(int $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetBurstLikes()
 * @method $this unsetLikeTs()
 * @method $this unsetLikers()
 * @method $this unsetLikes()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class BroadcastLikeCountResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'like_ts'     => 'int',
        'likes'       => 'int',
        'burst_likes' => 'int',
        'likers'      => 'Model\User[]',
    ];
}
