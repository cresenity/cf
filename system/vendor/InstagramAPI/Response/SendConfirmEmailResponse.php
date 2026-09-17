<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * SendConfirmEmailResponse.
 *
 * @method mixed getBody()
 * @method mixed getIsEmailLegit()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method mixed getTitle()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isBody()
 * @method bool isIsEmailLegit()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isTitle()
 * @method bool isZMessages()
 * @method $this setBody(mixed $value)
 * @method $this setIsEmailLegit(mixed $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setTitle(mixed $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetBody()
 * @method $this unsetIsEmailLegit()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetTitle()
 * @method $this unsetZMessages()
 */
class SendConfirmEmailResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'title'          => '',
        'is_email_legit' => '',
        'body'           => '',
    ];
}
