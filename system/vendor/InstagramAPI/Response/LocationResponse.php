<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * LocationResponse.
 *
 * @method mixed getMessage()
 * @method string getRequestId()
 * @method string getStatus()
 * @method Model\Location[] getVenues()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isRequestId()
 * @method bool isStatus()
 * @method bool isVenues()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setRequestId(string $value)
 * @method $this setStatus(string $value)
 * @method $this setVenues(Model\Location[] $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetRequestId()
 * @method $this unsetStatus()
 * @method $this unsetVenues()
 * @method $this unsetZMessages()
 */
class LocationResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'venues'     => 'Model\Location[]',
        'request_id' => 'string',
    ];
}
