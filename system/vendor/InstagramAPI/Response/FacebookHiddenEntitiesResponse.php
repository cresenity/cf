<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * FacebookHiddenEntitiesResponse.
 *
 * @method mixed getMessage()
 * @method Model\HiddenEntities getRecent()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isRecent()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setRecent(Model\HiddenEntities $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetRecent()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class FacebookHiddenEntitiesResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'recent' => 'Model\HiddenEntities',
    ];
}
