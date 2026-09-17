<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * MutedReelsResponse.
 *
 * @method mixed getBigList()
 * @method mixed getMessage()
 * @method string getNextMaxId()
 * @method mixed getPageSize()
 * @method string getStatus()
 * @method Model\User[] getUsers()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isBigList()
 * @method bool isMessage()
 * @method bool isNextMaxId()
 * @method bool isPageSize()
 * @method bool isStatus()
 * @method bool isUsers()
 * @method bool isZMessages()
 * @method $this setBigList(mixed $value)
 * @method $this setMessage(mixed $value)
 * @method $this setNextMaxId(string $value)
 * @method $this setPageSize(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setUsers(Model\User[] $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetBigList()
 * @method $this unsetMessage()
 * @method $this unsetNextMaxId()
 * @method $this unsetPageSize()
 * @method $this unsetStatus()
 * @method $this unsetUsers()
 * @method $this unsetZMessages()
 */
class MutedReelsResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'users'       => 'Model\User[]',
        'next_max_id' => 'string',
        'page_size'   => '',
        'big_list'    => '',
    ];
}
