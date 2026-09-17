<?php

defined('SYSPATH') OR die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @since Jun 23, 2019, 4:25:24 AM
 * @license Ittron Global Teknologi <ittron.co.id>
 */
class CDetector_Crawler_Provider_ExclusionsProvider extends CDetector_Crawler_AbstractProvider {

    /**
     * List of strings to remove from the user agent before running the crawler regex
     * Over a large list of user agents, this gives us about a 55% speed increase!
     *
     * @var array
     */
    protected $data = array(
        'Safari.[\d\.]*',
        'Firefox.[\d\.]*',
        ' Chrome.[\d\.]*',
        'Chromium.[\d\.]*',
        'MSIE.[\d\.]',
        'Opera\/[\d\.]*',
        'Mozilla.[\d\.]*',
        'AppleWebKit.[\d\.]*',
        'Trident.[\d\.]*',
        'Windows NT.[\d\.]*',
        'Android [\d\.]*',
        'Macintosh.',
        'Ubuntu',
        'Linux',
        '[ ]Intel',
        'Mac OS X [\d_]*',
        '(like )?Gecko(.[\d\.]*)?',
        'KHTML,',
        'CriOS.[\d\.]*',
        'CPU iPhone OS ([0-9_])* like Mac OS X',
        'CPU OS ([0-9_])* like Mac OS X',
        'iPod',
        'compatible',
        'x86_..',
        'i686',
        'x64',
        'X11',
        'rv:[\d\.]*',
        'Version.[\d\.]*',
        'WOW64',
        'Win64',
        'Dalvik.[\d\.]*',
        ' \.NET CLR [\d\.]*',
        'Presto.[\d\.]*',
        'Media Center PC',
        'BlackBerry',
        'Build',
        'Opera Mini\/\d{1,2}\.\d{1,2}\.[\d\.]*\/\d{1,2}\.',
        'Opera',
        ' \.NET[\d\.]*',
        'cubot',
        '; M bot',
        '; CRONO',
        '; B bot',
        '; IDbot',
        '; ID bot',
        '; POWER BOT',
        ';', // Remove the following characters ;
    );

}
