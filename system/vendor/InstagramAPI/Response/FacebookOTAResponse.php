<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * FacebookOTAResponse.
 *
 * @method mixed getBundles()
 * @method mixed getMessage()
 * @method string getRequestId()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isBundles()
 * @method bool isMessage()
 * @method bool isRequestId()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setBundles(mixed $value)
 * @method $this setMessage(mixed $value)
 * @method $this setRequestId(string $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetBundles()
 * @method $this unsetMessage()
 * @method $this unsetRequestId()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class FacebookOTAResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'bundles'    => '',
        'request_id' => 'string',
    ];
}
