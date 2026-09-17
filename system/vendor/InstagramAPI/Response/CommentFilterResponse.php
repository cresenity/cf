<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * CommentFilterResponse.
 *
 * @method mixed getConfigValue()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isConfigValue()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setConfigValue(mixed $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetConfigValue()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class CommentFilterResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'config_value' => '',
    ];
}
