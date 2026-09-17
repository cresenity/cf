<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * EditCollectionResponse.
 *
 * @method string getCollectionId()
 * @method string getCollectionName()
 * @method Model\Item getCoverMedia()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isCollectionId()
 * @method bool isCollectionName()
 * @method bool isCoverMedia()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setCollectionId(string $value)
 * @method $this setCollectionName(string $value)
 * @method $this setCoverMedia(Model\Item $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetCollectionId()
 * @method $this unsetCollectionName()
 * @method $this unsetCoverMedia()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class EditCollectionResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        Model\Collection::class, // Import property map.
    ];
}
