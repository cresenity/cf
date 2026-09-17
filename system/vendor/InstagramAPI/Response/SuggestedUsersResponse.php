<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * SuggestedUsersResponse.
 *
 * @method mixed getIsBackup()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\User[] getUsers()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isIsBackup()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isUsers()
 * @method bool isZMessages()
 * @method $this setIsBackup(mixed $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setUsers(Model\User[] $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetIsBackup()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetUsers()
 * @method $this unsetZMessages()
 */
class SuggestedUsersResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'users'     => 'Model\User[]',
        'is_backup' => '',
    ];
}
