<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * PostLiveViewerListResponse.
 *
 * @method mixed getMessage()
 * @method mixed getNextMaxId()
 * @method string getStatus()
 * @method int getTotalViewerCount()
 * @method Model\User[] getUsers()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isNextMaxId()
 * @method bool isStatus()
 * @method bool isTotalViewerCount()
 * @method bool isUsers()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setNextMaxId(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setTotalViewerCount(int $value)
 * @method $this setUsers(Model\User[] $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetNextMaxId()
 * @method $this unsetStatus()
 * @method $this unsetTotalViewerCount()
 * @method $this unsetUsers()
 * @method $this unsetZMessages()
 */
class PostLiveViewerListResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'users'              => 'Model\User[]',
        'next_max_id'        => '',
        'total_viewer_count' => 'int',
    ];
}
