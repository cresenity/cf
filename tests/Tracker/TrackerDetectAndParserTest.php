<?php
use PHPUnit\Framework\TestCase;

/**
 * CTracker bagian murni: deteksi perangkat/bahasa/crawler dan parser referer/user-agent dari string tetap.
 */
class TrackerDetectAndParserTest extends TestCase {
    const UA_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1';

    const UA_IPAD = 'Mozilla/5.0 (iPad; CPU OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1';

    const UA_ANDROID = 'Mozilla/5.0 (Linux; Android 13; SM-S911B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';

    const UA_CHROME_WIN = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.71 Safari/537.36';

    const UA_GOOGLEBOT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    /**
     * @param string $userAgent
     * @param array  $headers
     *
     * @return CTracker_Detect_MobileDetect
     */
    protected function detector($userAgent, array $headers = []) {
        return new CTracker_Detect_MobileDetect(array_merge(['HTTP_USER_AGENT' => $userAgent], $headers), $userAgent);
    }

    public function testDeviceKindPhoneTabletComputer() {
        $this->assertSame('Phone', $this->detector(self::UA_IPHONE)->getDeviceKind());
        $this->assertSame('Tablet', $this->detector(self::UA_IPAD)->getDeviceKind());
        $this->assertSame('Phone', $this->detector(self::UA_ANDROID)->getDeviceKind());
        $this->assertSame('Computer', $this->detector(self::UA_CHROME_WIN)->getDeviceKind());
        $this->assertTrue($this->detector(self::UA_CHROME_WIN)->isComputer());
        $this->assertFalse($this->detector(self::UA_IPHONE)->isComputer());
    }

    public function testDetectDeviceSummary() {
        $device = $this->detector(self::UA_IPHONE)->detectDevice();
        $this->assertSame('Phone', $device['kind']);
        $this->assertSame('iPhone', $device['model']);
        $this->assertTrue($device['is_mobile']);
        $this->assertFalse($device['is_robot']);

        $device = $this->detector(self::UA_GOOGLEBOT)->detectDevice();
        $this->assertTrue($device['is_robot']);
        $this->assertSame('Computer', $device['kind'], 'crawler tanpa tanda mobile = Computer');
    }

    public function testBrowserPlatformAndVersion() {
        $detector = $this->detector(self::UA_CHROME_WIN);
        $this->assertSame('Chrome', $detector->browser());
        $this->assertSame('Windows', $detector->platform());
        $this->assertSame('120.0.6099.71', $detector->version('Chrome'));
        $this->assertSame('10.0', $detector->version('Windows NT'));
        $this->assertSame(120.0609971, $detector->version('Chrome', CTracker_Detect_MobileDetect::VERSION_TYPE_FLOAT), 'versi float: titik setelah yang pertama dibuang (konvensi Mobile_Detect)');

        $android = $this->detector(self::UA_ANDROID);
        $this->assertSame('AndroidOS', $android->platform());
        $this->assertSame('13', $android->version('Android'));
        $this->assertSame('WebKit', $android->device(), 'urutan aturan: desktop dulu (WebKit ada di daftar utilitas), lalu phone — dikunci apa adanya');
        $this->assertSame('iOS', $this->detector(self::UA_IPHONE)->platform());
        $this->assertSame('Safari', $this->detector(self::UA_IPHONE)->browser());
        $this->assertFalse($detector->version(''));
    }

    public function testCrawlerDetection() {
        $this->assertTrue((new CTracker_Detect_CrawlerDetect([], self::UA_GOOGLEBOT))->isRobot());
        $this->assertFalse((new CTracker_Detect_CrawlerDetect([], self::UA_CHROME_WIN))->isRobot());
        $this->assertTrue($this->detector(self::UA_GOOGLEBOT)->isRobot());
        $this->assertTrue($this->detector(self::UA_CHROME_WIN)->isRobot('curl/8.4.0'), 'user agent eksplisit menang');
    }

    public function testLanguagesAreOrderedByQuality() {
        $detector = $this->detector(self::UA_CHROME_WIN, ['HTTP_ACCEPT_LANGUAGE' => 'en-US;q=0.8,id-ID,id;q=0.9,fr;q=0.5']);
        $this->assertSame(['id-id', 'id', 'en-us', 'fr'], $detector->languages());
        $this->assertSame(['de', 'en'], $detector->languages('de,en;q=0.5'));
        $this->assertSame([], $this->detector(self::UA_CHROME_WIN)->languages(), 'tanpa header → kosong');

        $language = new CTracker_Detect_LanguageDetect(['HTTP_USER_AGENT' => self::UA_CHROME_WIN, 'HTTP_ACCEPT_LANGUAGE' => 'id-ID,id;q=0.9,en;q=0.8'], self::UA_CHROME_WIN);
        $this->assertSame(['preference' => 'id-id', 'language_range' => 'id-id,id,en'], $language->detectLanguage());
        $this->assertSame('en', (new CTracker_Detect_LanguageDetect(['HTTP_USER_AGENT' => self::UA_CHROME_WIN], self::UA_CHROME_WIN))->getLanguagePreference(), 'default en');
    }

    public function testUserAgentParserSplitsBrowserOsAndDevice() {
        $parser = new CTracker_Parser_UserAgentParser(sys_get_temp_dir(), self::UA_CHROME_WIN);
        $this->assertSame('Chrome', $parser->userAgent->family);
        $this->assertSame('120.0.6099', $parser->getUserAgentVersion());
        $this->assertSame('Windows', $parser->operatingSystem->family);
        $this->assertSame('10', $parser->getOperatingSystemVersion());
        $this->assertSame(self::UA_CHROME_WIN, $parser->originalUserAgent);

        $iphone = new CTracker_Parser_UserAgentParser(sys_get_temp_dir(), self::UA_IPHONE);
        $this->assertSame('Mobile Safari', $iphone->userAgent->family);
        $this->assertSame('iOS', $iphone->operatingSystem->family);
        $this->assertSame('17.2', $iphone->getOperatingSystemVersion());
        $this->assertSame('iPhone', $iphone->device->family);
    }

    public function testRefererParserRecognisesSearchEnginesAndSocial() {
        $parser = (new CTracker_Parser_RefererParser())->parse('https://www.google.com/search?q=cresenity+framework', 'https://cresenity.com/');
        $this->assertTrue($parser->isKnown());
        $this->assertSame('search', $parser->getMedium());
        $this->assertSame('Google', $parser->getSource());
        $this->assertSame('cresenity framework', $parser->getSearchTerm());

        $social = (new CTracker_Parser_RefererParser())->parse('https://www.facebook.com/', 'https://cresenity.com/');
        $this->assertSame('social', $social->getMedium());
        $this->assertSame('Facebook', $social->getSource());
        $this->assertNull($social->getSearchTerm());

        $unknown = (new CTracker_Parser_RefererParser())->parse('https://situs-tak-dikenal.example/halaman', 'https://cresenity.com/');
        $this->assertFalse($unknown->isKnown());
        $this->assertNull($unknown->getMedium());
        $this->assertNull($unknown->getSource());

        $internal = (new CTracker_Parser_RefererParser())->parse('https://cresenity.com/lain', 'https://cresenity.com/');
        $this->assertFalse($internal->isKnown(), 'referer internal (host sama) tidak dianggap sumber');
    }
}
