<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * FBSearchResponse.
 *
 * @method bool getHasMore()
 * @method mixed getHashtags()
 * @method mixed getMessage()
 * @method mixed getPlaces()
 * @method string getRankToken()
 * @method string getStatus()
 * @method mixed getUsers()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isHasMore()
 * @method bool isHashtags()
 * @method bool isMessage()
 * @method bool isPlaces()
 * @method bool isRankToken()
 * @method bool isStatus()
 * @method bool isUsers()
 * @method bool isZMessages()
 * @method $this setHasMore(bool $value)
 * @method $this setHashtags(mixed $value)
 * @method $this setMessage(mixed $value)
 * @method $this setPlaces(mixed $value)
 * @method $this setRankToken(string $value)
 * @method $this setStatus(string $value)
 * @method $this setUsers(mixed $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetHasMore()
 * @method $this unsetHashtags()
 * @method $this unsetMessage()
 * @method $this unsetPlaces()
 * @method $this unsetRankToken()
 * @method $this unsetStatus()
 * @method $this unsetUsers()
 * @method $this unsetZMessages()
 */
class FBSearchResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'has_more'   => 'bool',
        'hashtags'   => '',
        'users'      => '',
        'places'     => '',
        'rank_token' => 'string',
    ];
}
