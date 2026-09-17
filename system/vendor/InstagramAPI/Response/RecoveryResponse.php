<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * RecoveryResponse.
 *
 * @method string getBody()
 * @method mixed getMessage()
 * @method bool getPhoneNumberValid()
 * @method string getStatus()
 * @method string getTitle()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isBody()
 * @method bool isMessage()
 * @method bool isPhoneNumberValid()
 * @method bool isStatus()
 * @method bool isTitle()
 * @method bool isZMessages()
 * @method $this setBody(string $value)
 * @method $this setMessage(mixed $value)
 * @method $this setPhoneNumberValid(bool $value)
 * @method $this setStatus(string $value)
 * @method $this setTitle(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetBody()
 * @method $this unsetMessage()
 * @method $this unsetPhoneNumberValid()
 * @method $this unsetStatus()
 * @method $this unsetTitle()
 * @method $this unsetZMessages()
 */
class RecoveryResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'phone_number_valid' => 'bool',
        'title'              => 'string',
        'body'               => 'string',
    ];
}
