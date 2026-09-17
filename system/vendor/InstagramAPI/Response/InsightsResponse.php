<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * InsightsResponse.
 *
 * @method Model\Insights[] getInstagramUser()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isInstagramUser()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setInstagramUser(Model\Insights[] $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetInstagramUser()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class InsightsResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'instagram_user' => 'Model\Insights[]',
    ];
}
