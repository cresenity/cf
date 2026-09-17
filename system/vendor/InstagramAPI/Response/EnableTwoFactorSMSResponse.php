<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * EnableTwoFactorSMSResponse.
 *
 * @method mixed getBackupCodes()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isBackupCodes()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setBackupCodes(mixed $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetBackupCodes()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class EnableTwoFactorSMSResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'backup_codes' => '',
    ];
}
