<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * ReportExploreMediaResponse.
 *
 * @method mixed getExploreReportStatus()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isExploreReportStatus()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setExploreReportStatus(mixed $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetExploreReportStatus()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class ReportExploreMediaResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'explore_report_status' => '',
    ];
}
