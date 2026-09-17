<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * MsisdnHeaderResponse.
 *
 * @method mixed getMessage()
 * @method string getPhoneNumber()
 * @method string getStatus()
 * @method string getUrl()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isPhoneNumber()
 * @method bool isStatus()
 * @method bool isUrl()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setPhoneNumber(string $value)
 * @method $this setStatus(string $value)
 * @method $this setUrl(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetPhoneNumber()
 * @method $this unsetStatus()
 * @method $this unsetUrl()
 * @method $this unsetZMessages()
 */
class MsisdnHeaderResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'phone_number' => 'string',
        'url'          => 'string',
    ];
}
