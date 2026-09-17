<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * ProfileNoticeResponse.
 *
 * @method bool getHasChangePasswordMegaphone()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isHasChangePasswordMegaphone()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setHasChangePasswordMegaphone(bool $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetHasChangePasswordMegaphone()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class ProfileNoticeResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'has_change_password_megaphone' => 'bool',
    ];
}
