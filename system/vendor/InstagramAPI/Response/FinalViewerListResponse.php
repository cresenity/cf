<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * FinalViewerListResponse.
 *
 * @method mixed getMessage()
 * @method string getStatus()
 * @method int getTotalUniqueViewerCount()
 * @method Model\User[] getUsers()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isTotalUniqueViewerCount()
 * @method bool isUsers()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setTotalUniqueViewerCount(int $value)
 * @method $this setUsers(Model\User[] $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetTotalUniqueViewerCount()
 * @method $this unsetUsers()
 * @method $this unsetZMessages()
 */
class FinalViewerListResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'users'                     => 'Model\User[]',
        'total_unique_viewer_count' => 'int',
    ];
}
