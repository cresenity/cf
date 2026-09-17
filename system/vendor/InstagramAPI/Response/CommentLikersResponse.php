<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * CommentLikersResponse.
 *
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\User[] getUsers()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isUsers()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setUsers(Model\User[] $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetUsers()
 * @method $this unsetZMessages()
 */
class CommentLikersResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'users' => 'Model\User[]',
    ];
}
