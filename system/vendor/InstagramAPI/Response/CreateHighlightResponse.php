<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * CreateHighlightResponse.
 *
 * @method mixed getMessage()
 * @method Model\Reel getReel()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isReel()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setReel(Model\Reel $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetReel()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class CreateHighlightResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'reel' => 'Model\Reel',
    ];
}
