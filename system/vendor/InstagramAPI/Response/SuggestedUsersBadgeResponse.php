<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * SuggestedUsersBadgeResponse.
 *
 * @method mixed getMessage()
 * @method string[] getNewSuggestionIds()
 * @method mixed getShouldBadge()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isNewSuggestionIds()
 * @method bool isShouldBadge()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setNewSuggestionIds(string[] $value)
 * @method $this setShouldBadge(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetNewSuggestionIds()
 * @method $this unsetShouldBadge()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class SuggestedUsersBadgeResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'should_badge'       => '',
        'new_suggestion_ids' => 'string[]',
    ];
}
