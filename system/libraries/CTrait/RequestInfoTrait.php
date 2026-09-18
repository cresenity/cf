<?php

/**
 * Description of RequestInfoTrait.
 */
use UAParser\Parser;
use UAParser\Result\Client;

trait CTrait_RequestInfoTrait {
    protected static $userAgentParser;

    public static function userAgent() {
        return CHTTP::request()->server('HTTP_USER_AGENT');
    }

    /**
     * @param string $userAgent
     *
     * @return \UAParser\Result\Client
     */
    public static function getUserAgentParser($userAgent = null) {
        if ($userAgent == null) {
            $userAgent = static::userAgent();
        }
        //permintaan tanpa User-Agent itu lumrah — CLI, cron, klien API, curl
        //polos — dan pengurainya menuntut string. Tanpa penjaga ini, mencatat
        //aktivitas dari jalur seperti itu melempar TypeError dan menjatuhkan
        //pekerjaan yang sedang berjalan, bukan sekadar gagal mencatat.
        $userAgent = (string) $userAgent;
        if (static::$userAgentParser == null) {
            static::$userAgentParser = [];
        }
        if (!isset(static::$userAgentParser[$userAgent])) {
            static::$userAgentParser[$userAgent] = Parser::create()->parse($userAgent);
        }

        return static::$userAgentParser[$userAgent];
    }

    public static function browserName($userAgent = null) {
        return static::getUserAgentParser($userAgent)->ua->family;
    }

    public static function browserVersion($userAgent = null) {
        $userAgentParser = static::getUserAgentParser($userAgent);

        return $userAgentParser->ua->major
                . ($userAgentParser->ua->minor !== null ? '.' . $userAgentParser->ua->minor : '')
                . ($userAgentParser->ua->patch !== null ? '.' . $userAgentParser->ua->patch : '');
    }

    public static function platformName($userAgent = null) {
        $userAgentParser = static::getUserAgentParser($userAgent);
        $platformName = '';

        try {
            $platformName = $userAgentParser->os->family;
        } catch (Exception $ex) {
            // do nothing
        }

        return $platformName;
    }

    public static function platformVersion($userAgent = null) {
        $userAgentParser = static::getUserAgentParser($userAgent);
        $platformVersion = '';

        try {
            $platformVersion = $userAgentParser->os->major
                    . ($userAgentParser->os->minor !== null ? '.' . $userAgentParser->os->minor : '')
                    . ($userAgentParser->os->patch !== null ? '.' . $userAgentParser->os->patch : '');
        } catch (Exception $ex) {
            // do nothing
        }

        return $platformVersion;
    }

    /**
     * Fetch the Remote Address.
     *
     * @return string
     */
    public static function remoteAddress() {
        $clientIp = null;
        $request = CHTTP::request();
        $headers = $request->header();
        if (isset($headers['x-forwarded-for'])) {
            $clientIp = carr::get($headers, 'x-forwarded-for.0');
        }
        if (strpos($clientIp, ',') !== false) {
            $clientIp = trim(carr::get(explode(',', $clientIp), 0));
        }

        if ($clientIp == null) {
            $clientIp = $request->getClientIp();
        }

        return $clientIp;
    }
}
