<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * PresenceStatusResponse.
 *
 * @method bool getDisabled()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method bool getThreadPresenceDisabled()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isDisabled()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isThreadPresenceDisabled()
 * @method bool isZMessages()
 * @method $this setDisabled(bool $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setThreadPresenceDisabled(bool $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetDisabled()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetThreadPresenceDisabled()
 * @method $this unsetZMessages()
 */
class PresenceStatusResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'disabled'                 => 'bool',
        'thread_presence_disabled' => 'bool',
    ];
}
