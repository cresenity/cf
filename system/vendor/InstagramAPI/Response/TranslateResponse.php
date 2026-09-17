<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * TranslateResponse.
 *
 * @method Model\CommentTranslations[] getCommentTranslations()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isCommentTranslations()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setCommentTranslations(Model\CommentTranslations[] $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetCommentTranslations()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class TranslateResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'comment_translations' => 'Model\CommentTranslations[]',
    ];
}
