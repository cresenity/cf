<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * SuggestedBroadcastsResponse.
 *
 * @method Model\Broadcast[] getBroadcasts()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isBroadcasts()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setBroadcasts(Model\Broadcast[] $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetBroadcasts()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class SuggestedBroadcastsResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'broadcasts' => 'Model\Broadcast[]',
    ];
}
