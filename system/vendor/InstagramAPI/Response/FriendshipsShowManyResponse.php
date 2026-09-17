<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * FriendshipsShowManyResponse.
 *
 * @method Model\UnpredictableKeys\FriendshipStatusUnpredictableContainer getFriendshipStatuses()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isFriendshipStatuses()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setFriendshipStatuses(Model\UnpredictableKeys\FriendshipStatusUnpredictableContainer $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetFriendshipStatuses()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class FriendshipsShowManyResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'friendship_statuses' => 'Model\UnpredictableKeys\FriendshipStatusUnpredictableContainer',
    ];
}
