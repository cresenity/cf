<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * MegaphoneLogResponse.
 *
 * @method mixed getMessage()
 * @method string getStatus()
 * @method mixed getSuccess()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isSuccess()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setSuccess(mixed $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetSuccess()
 * @method $this unsetZMessages()
 */
class MegaphoneLogResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'success' => '',
    ];
}
