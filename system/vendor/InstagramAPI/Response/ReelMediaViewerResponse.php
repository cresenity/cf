<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * ReelMediaViewerResponse.
 *
 * @method mixed getMessage()
 * @method string getNextMaxId()
 * @method string getStatus()
 * @method int getTotalViewerCount()
 * @method int getUserCount()
 * @method Model\User[] getUsers()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isNextMaxId()
 * @method bool isStatus()
 * @method bool isTotalViewerCount()
 * @method bool isUserCount()
 * @method bool isUsers()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setNextMaxId(string $value)
 * @method $this setStatus(string $value)
 * @method $this setTotalViewerCount(int $value)
 * @method $this setUserCount(int $value)
 * @method $this setUsers(Model\User[] $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetNextMaxId()
 * @method $this unsetStatus()
 * @method $this unsetTotalViewerCount()
 * @method $this unsetUserCount()
 * @method $this unsetUsers()
 * @method $this unsetZMessages()
 */
class ReelMediaViewerResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'users'              => 'Model\User[]',
        'next_max_id'        => 'string',
        'user_count'         => 'int',
        'total_viewer_count' => 'int',
    ];
}
