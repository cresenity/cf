<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * Used for generic API responses that don't contain any extra data.
 *
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class GenericResponse extends Response
{
    // WARNING: Don't add any values here. Create new responses.
    public static $JSON_PROPERTY_MAP = [];
}
