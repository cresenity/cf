<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * ReelsMediaResponse.
 *
 * @method mixed getMessage()
 * @method Model\UnpredictableKeys\ReelUnpredictableContainer getReels()
 * @method Model\Reel[] getReelsMedia()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isReels()
 * @method bool isReelsMedia()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setReels(Model\UnpredictableKeys\ReelUnpredictableContainer $value)
 * @method $this setReelsMedia(Model\Reel[] $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetReels()
 * @method $this unsetReelsMedia()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class ReelsMediaResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'reels_media' => 'Model\Reel[]',
        'reels'       => 'Model\UnpredictableKeys\ReelUnpredictableContainer',
    ];
}
