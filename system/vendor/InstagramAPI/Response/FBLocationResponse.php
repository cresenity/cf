<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * FBLocationResponse.
 *
 * @method bool getHasMore()
 * @method Model\LocationItem[] getItems()
 * @method mixed getMessage()
 * @method string getRankToken()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isHasMore()
 * @method bool isItems()
 * @method bool isMessage()
 * @method bool isRankToken()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setHasMore(bool $value)
 * @method $this setItems(Model\LocationItem[] $value)
 * @method $this setMessage(mixed $value)
 * @method $this setRankToken(string $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetHasMore()
 * @method $this unsetItems()
 * @method $this unsetMessage()
 * @method $this unsetRankToken()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class FBLocationResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'has_more'   => 'bool',
        'items'      => 'Model\LocationItem[]',
        'rank_token' => 'string',
    ];
}
