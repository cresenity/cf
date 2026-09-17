<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * MediaLikersResponse.
 *
 * @method mixed getMessage()
 * @method string getStatus()
 * @method int getUserCount()
 * @method Model\User[] getUsers()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isUserCount()
 * @method bool isUsers()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setUserCount(int $value)
 * @method $this setUsers(Model\User[] $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetUserCount()
 * @method $this unsetUsers()
 * @method $this unsetZMessages()
 */
class MediaLikersResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'user_count' => 'int',
        'users'      => 'Model\User[]',
    ];
}
