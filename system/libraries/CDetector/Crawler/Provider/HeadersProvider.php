<?php

defined('SYSPATH') OR die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @since Jun 23, 2019, 4:24:11 AM
 * @license Ittron Global Teknologi <ittron.co.id>
 */
class CDetector_Crawler_Provider_HeadersProvider extends CDetector_Crawler_AbstractProvider {

    /**
     * All possible HTTP headers that represent the user agent string.
     *
     * @var array
     */
    protected $data = array(
        // The default User-Agent string.
        'HTTP_USER_AGENT',
        // Header can occur on devices using Opera Mini.
        'HTTP_X_OPERAMINI_PHONE_UA',
        // Vodafone specific header: http://www.seoprinciple.com/mobile-web-community-still-angry-at-vodafone/24/
        'HTTP_X_DEVICE_USER_AGENT',
        'HTTP_X_ORIGINAL_USER_AGENT',
        'HTTP_X_SKYFIRE_PHONE',
        'HTTP_X_BOLT_PHONE_UA',
        'HTTP_DEVICE_STOCK_UA',
        'HTTP_X_UCBROWSER_DEVICE_UA',
        // Sometimes, bots (especially Google) use a genuine user agent, but fill this header in with their email address
        'HTTP_FROM',
        'HTTP_X_SCANNER', // Seen in use by Netsparker
    );

}
