<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * CommentFilterKeywordsResponse.
 *
 * @method mixed getKeywords()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isKeywords()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setKeywords(mixed $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetKeywords()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class CommentFilterKeywordsResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'keywords' => '',
    ];
}
