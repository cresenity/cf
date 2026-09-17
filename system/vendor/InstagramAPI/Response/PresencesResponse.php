<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * PresencesResponse.
 *
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\UnpredictableKeys\PresenceUnpredictableContainer getUserPresence()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isUserPresence()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setUserPresence(Model\UnpredictableKeys\PresenceUnpredictableContainer $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetUserPresence()
 * @method $this unsetZMessages()
 */
class PresencesResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'user_presence' => 'Model\UnpredictableKeys\PresenceUnpredictableContainer',
    ];
}
