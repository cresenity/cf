<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * TagRelatedResponse.
 *
 * @method mixed getMessage()
 * @method Model\Related[] getRelated()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isRelated()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setRelated(Model\Related[] $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetRelated()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class TagRelatedResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'related' => 'Model\Related[]',
    ];
}
