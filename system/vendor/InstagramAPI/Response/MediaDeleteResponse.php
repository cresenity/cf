<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * MediaDeleteResponse.
 *
 * @method mixed getDidDelete()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isDidDelete()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setDidDelete(mixed $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetDidDelete()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class MediaDeleteResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'did_delete' => '',
    ];
}
