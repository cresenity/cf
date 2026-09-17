<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * RecentSearchesResponse.
 *
 * @method mixed getMessage()
 * @method Model\Suggested[] getRecent()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isRecent()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setRecent(Model\Suggested[] $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetRecent()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class RecentSearchesResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'recent' => 'Model\Suggested[]',
    ];
}
