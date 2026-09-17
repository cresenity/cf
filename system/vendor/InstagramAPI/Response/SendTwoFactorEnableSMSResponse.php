<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * SendTwoFactorEnableSMSResponse.
 *
 * @method mixed getMessage()
 * @method mixed getObfuscatedPhoneNumber()
 * @method Model\PhoneVerificationSettings getPhoneVerificationSettings()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isObfuscatedPhoneNumber()
 * @method bool isPhoneVerificationSettings()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setObfuscatedPhoneNumber(mixed $value)
 * @method $this setPhoneVerificationSettings(Model\PhoneVerificationSettings $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetObfuscatedPhoneNumber()
 * @method $this unsetPhoneVerificationSettings()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class SendTwoFactorEnableSMSResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'phone_verification_settings' => 'Model\PhoneVerificationSettings',
        'obfuscated_phone_number'     => '',
    ];
}
