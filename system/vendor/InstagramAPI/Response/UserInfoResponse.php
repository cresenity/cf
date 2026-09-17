<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * UserInfoResponse.
 *
 * @method mixed getMegaphone()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\User getUser()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMegaphone()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isUser()
 * @method bool isZMessages()
 * @method $this setMegaphone(mixed $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setUser(Model\User $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMegaphone()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetUser()
 * @method $this unsetZMessages()
 */
class UserInfoResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'megaphone' => '',
        'user'      => 'Model\User',
    ];
}
