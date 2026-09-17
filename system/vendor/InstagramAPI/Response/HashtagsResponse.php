<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * HashtagsResponse.
 *
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\Hashtag[] getTags()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isTags()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setTags(Model\Hashtag[] $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetTags()
 * @method $this unsetZMessages()
 */
class HashtagsResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'tags' => 'Model\Hashtag[]',
    ];
}
