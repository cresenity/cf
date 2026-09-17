<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * TwoFactorLoginSMSResponse.
 *
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\TwoFactorInfo getTwoFactorInfo()
 * @method bool getTwoFactorRequired()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isTwoFactorInfo()
 * @method bool isTwoFactorRequired()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setTwoFactorInfo(Model\TwoFactorInfo $value)
 * @method $this setTwoFactorRequired(bool $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetTwoFactorInfo()
 * @method $this unsetTwoFactorRequired()
 * @method $this unsetZMessages()
 */
class TwoFactorLoginSMSResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'two_factor_required' => 'bool',
        'two_factor_info'     => 'Model\TwoFactorInfo',
    ];
}
