<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * CommentCategoryFilterResponse.
 *
 * @method mixed getDisabled()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isDisabled()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setDisabled(mixed $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetDisabled()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class CommentCategoryFilterResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'disabled' => '',
    ];
}
