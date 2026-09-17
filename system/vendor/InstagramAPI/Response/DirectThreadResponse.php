<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * DirectThreadResponse.
 *
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\DirectThread getThread()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isThread()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setThread(Model\DirectThread $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetThread()
 * @method $this unsetZMessages()
 */
class DirectThreadResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'thread' => 'Model\DirectThread',
    ];
}
