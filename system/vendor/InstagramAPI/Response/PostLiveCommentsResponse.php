<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * PostLiveCommentsResponse.
 *
 * @method Model\LiveComment[] getComments()
 * @method mixed getEndingOffset()
 * @method mixed getMessage()
 * @method mixed getNextFetchOffset()
 * @method Model\LiveComment[] getPinnedComments()
 * @method mixed getStartingOffset()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isComments()
 * @method bool isEndingOffset()
 * @method bool isMessage()
 * @method bool isNextFetchOffset()
 * @method bool isPinnedComments()
 * @method bool isStartingOffset()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setComments(Model\LiveComment[] $value)
 * @method $this setEndingOffset(mixed $value)
 * @method $this setMessage(mixed $value)
 * @method $this setNextFetchOffset(mixed $value)
 * @method $this setPinnedComments(Model\LiveComment[] $value)
 * @method $this setStartingOffset(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetComments()
 * @method $this unsetEndingOffset()
 * @method $this unsetMessage()
 * @method $this unsetNextFetchOffset()
 * @method $this unsetPinnedComments()
 * @method $this unsetStartingOffset()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class PostLiveCommentsResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'starting_offset'   => '',
        'ending_offset'     => '',
        'next_fetch_offset' => '',
        'comments'          => 'Model\LiveComment[]',
        'pinned_comments'   => 'Model\LiveComment[]',
    ];
}
