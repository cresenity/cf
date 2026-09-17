<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * BootstrapUsersResponse.
 *
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\Surface[] getSurfaces()
 * @method Model\User[] getUsers()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isSurfaces()
 * @method bool isUsers()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setSurfaces(Model\Surface[] $value)
 * @method $this setUsers(Model\User[] $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetSurfaces()
 * @method $this unsetUsers()
 * @method $this unsetZMessages()
 */
class BootstrapUsersResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'surfaces' => 'Model\Surface[]',
        'users'    => 'Model\User[]',
    ];
}
