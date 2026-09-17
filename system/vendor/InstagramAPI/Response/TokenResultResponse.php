<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * TokenResultResponse.
 *
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\Token getToken()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isToken()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setToken(Model\Token $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetToken()
 * @method $this unsetZMessages()
 */
class TokenResultResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'token' => 'Model\Token',
    ];
}
