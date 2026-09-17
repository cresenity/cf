<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * StickerAssetsResponse.
 *
 * @method mixed getMessage()
 * @method Model\StaticStickers[] getStaticStickers()
 * @method string getStatus()
 * @method mixed getVersion()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isStaticStickers()
 * @method bool isStatus()
 * @method bool isVersion()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setStaticStickers(Model\StaticStickers[] $value)
 * @method $this setStatus(string $value)
 * @method $this setVersion(mixed $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetStaticStickers()
 * @method $this unsetStatus()
 * @method $this unsetVersion()
 * @method $this unsetZMessages()
 */
class StickerAssetsResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'version'         => '',
        'static_stickers' => 'Model\StaticStickers[]',
    ];
}
