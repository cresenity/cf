<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * ReviewPreferenceResponse.
 *
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\User getUser()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isUser()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setUser(Model\User $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetUser()
 * @method $this unsetZMessages()
 */
class ReviewPreferenceResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'user' => 'Model\User',
    ];
}
