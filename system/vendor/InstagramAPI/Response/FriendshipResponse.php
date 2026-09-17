<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * FriendshipResponse.
 *
 * @method Model\FriendshipStatus getFriendshipStatus()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isFriendshipStatus()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setFriendshipStatus(Model\FriendshipStatus $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetFriendshipStatus()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class FriendshipResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'friendship_status' => 'Model\FriendshipStatus',
    ];
}
