<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * VerifySMSCodeResponse.
 *
 * @method mixed getMessage()
 * @method string getPhoneNumber()
 * @method string getStatus()
 * @method bool getVerified()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isPhoneNumber()
 * @method bool isStatus()
 * @method bool isVerified()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setPhoneNumber(string $value)
 * @method $this setStatus(string $value)
 * @method $this setVerified(bool $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetPhoneNumber()
 * @method $this unsetStatus()
 * @method $this unsetVerified()
 * @method $this unsetZMessages()
 */
class VerifySMSCodeResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'verified'     => 'bool',
        'phone_number' => 'string',
    ];
}
