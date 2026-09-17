<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * TopLiveStatusResponse.
 *
 * @method Model\BroadcastStatusItem[] getBroadcastStatusItems()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isBroadcastStatusItems()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setBroadcastStatusItems(Model\BroadcastStatusItem[] $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetBroadcastStatusItems()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class TopLiveStatusResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'broadcast_status_items' => 'Model\BroadcastStatusItem[]',
    ];
}
