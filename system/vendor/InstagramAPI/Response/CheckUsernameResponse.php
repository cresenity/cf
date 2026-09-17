<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * CheckUsernameResponse.
 *
 * @method mixed getAvailable()
 * @method mixed getError()
 * @method mixed getErrorType()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method string getUsername()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isAvailable()
 * @method bool isError()
 * @method bool isErrorType()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isUsername()
 * @method bool isZMessages()
 * @method $this setAvailable(mixed $value)
 * @method $this setError(mixed $value)
 * @method $this setErrorType(mixed $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setUsername(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetAvailable()
 * @method $this unsetError()
 * @method $this unsetErrorType()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetUsername()
 * @method $this unsetZMessages()
 */
class CheckUsernameResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'username'   => 'string',
        'available'  => '',
        'error'      => '',
        'error_type' => '',
    ];
}
