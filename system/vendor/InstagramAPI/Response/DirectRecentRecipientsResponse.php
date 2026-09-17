<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * DirectRecentRecipientsResponse.
 *
 * @method mixed getExpirationInterval()
 * @method mixed getMessage()
 * @method mixed getRecentRecipients()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isExpirationInterval()
 * @method bool isMessage()
 * @method bool isRecentRecipients()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setExpirationInterval(mixed $value)
 * @method $this setMessage(mixed $value)
 * @method $this setRecentRecipients(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetExpirationInterval()
 * @method $this unsetMessage()
 * @method $this unsetRecentRecipients()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class DirectRecentRecipientsResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'expiration_interval' => '',
        'recent_recipients'   => '',
    ];
}
