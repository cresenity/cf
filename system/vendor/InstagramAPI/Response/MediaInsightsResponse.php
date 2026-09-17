<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * MediaInsightsResponse.
 *
 * @method Model\MediaInsights[] getMediaOrganicInsights()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMediaOrganicInsights()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setMediaOrganicInsights(Model\MediaInsights[] $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMediaOrganicInsights()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class MediaInsightsResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'media_organic_insights' => 'Model\MediaInsights[]',
    ];
}
