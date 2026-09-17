<?php

namespace InstagramAPI\Response\Model;

use InstagramAPI\AutoPropertyMapper;

/**
 * Text.
 *
 * @method string getText()
 * @method bool isText()
 * @method $this setText(string $value)
 * @method $this unsetText()
 */
class Text extends AutoPropertyMapper
{
    public static $JSON_PROPERTY_MAP = [
        'text' => 'string',
    ];
}
