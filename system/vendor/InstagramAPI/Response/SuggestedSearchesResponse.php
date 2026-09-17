<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * SuggestedSearchesResponse.
 *
 * @method mixed getMessage()
 * @method string getRankToken()
 * @method string getStatus()
 * @method Model\Suggested[] getSuggested()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isRankToken()
 * @method bool isStatus()
 * @method bool isSuggested()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setRankToken(string $value)
 * @method $this setStatus(string $value)
 * @method $this setSuggested(Model\Suggested[] $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetRankToken()
 * @method $this unsetStatus()
 * @method $this unsetSuggested()
 * @method $this unsetZMessages()
 */
class SuggestedSearchesResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'suggested'  => 'Model\Suggested[]',
        'rank_token' => 'string',
    ];
}
