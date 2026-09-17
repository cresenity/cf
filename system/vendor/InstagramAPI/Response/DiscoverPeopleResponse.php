<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * DiscoverPeopleResponse.
 *
 * @method string getMaxId()
 * @method mixed getMessage()
 * @method bool getMoreAvailable()
 * @method Model\SuggestedUsers getNewSuggestedUsers()
 * @method string getStatus()
 * @method Model\SuggestedUsers getSuggestedUsers()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMaxId()
 * @method bool isMessage()
 * @method bool isMoreAvailable()
 * @method bool isNewSuggestedUsers()
 * @method bool isStatus()
 * @method bool isSuggestedUsers()
 * @method bool isZMessages()
 * @method $this setMaxId(string $value)
 * @method $this setMessage(mixed $value)
 * @method $this setMoreAvailable(bool $value)
 * @method $this setNewSuggestedUsers(Model\SuggestedUsers $value)
 * @method $this setStatus(string $value)
 * @method $this setSuggestedUsers(Model\SuggestedUsers $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMaxId()
 * @method $this unsetMessage()
 * @method $this unsetMoreAvailable()
 * @method $this unsetNewSuggestedUsers()
 * @method $this unsetStatus()
 * @method $this unsetSuggestedUsers()
 * @method $this unsetZMessages()
 */
class DiscoverPeopleResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'more_available'      => 'bool',
        'max_id'              => 'string',
        'suggested_users'     => 'Model\SuggestedUsers',
        'new_suggested_users' => 'Model\SuggestedUsers',
    ];
}
