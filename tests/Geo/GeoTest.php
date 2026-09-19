<?php
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * HTTP client palsu untuk provider geocoder: merekam request, membalas body tetap.
 */
class UjiGeo_FakeHttpClient implements Http\Client\HttpClient {
    /** @var RequestInterface[] */
    public $requests = [];

    /** @var int */
    public $status;

    /** @var string */
    public $body;

    /**
     * @param string $body
     * @param int    $status
     */
    public function __construct($body, $status = 200) {
        $this->body = $body;
        $this->status = $status;
    }

    public function sendRequest(RequestInterface $request) {
        $this->requests[] = $request;

        return new Response($this->status, ['Content-Type' => 'application/json'], $this->body);
    }
}

/**
 * CGeo: koordinat & jarak, model alamat, tipe spasial WKT/GeoJSON, provider Nominatim (HTTP palsu), GeoIP2 lokal.
 */
class GeoTest extends TestCase {
    public function testCoordinateValidationAndAccessors() {
        $jakarta = CGeo::createCoordinate(-6.2, 106.816666);
        $this->assertInstanceOf(CGeo_Location_Coordinate::class, $jakarta);
        $this->assertSame(-6.2, $jakarta->getLat());
        $this->assertSame(106.816666, $jakarta->getLng());
        $this->assertSame('World Geodetic System  1984', $jakarta->getEllipsoid()->getName());
        $this->assertSame(6378137.0, $jakarta->getEllipsoid()->getA());
        $this->expectException(InvalidArgumentException::class);
        new CGeo_Location_Coordinate(91, 0);
    }

    public function testLongitudeOutOfRangeIsRejected() {
        $this->expectException(InvalidArgumentException::class);
        new CGeo_Location_Coordinate(0, 181);
    }

    public function testHaversineAndVincentyDistances() {
        $jakarta = CGeo::createCoordinate(-6.2, 106.816666);
        $surabaya = CGeo::createCoordinate(-7.257472, 112.752088);
        $haversine = $jakarta->getDistance($surabaya);
        $this->assertEqualsWithDelta(663000, $haversine, 5000, 'Jakarta–Surabaya ± 663 km (Haversine default)');
        $vincenty = $jakarta->getDistance($surabaya, new CGeo_Location_Distance_Vincenty());
        $this->assertEqualsWithDelta($haversine, $vincenty, 3000, 'Vincenty (ellipsoid) beda < 0,5% dari Haversine (bola)');
        $this->assertSame(0.0, $jakarta->getDistance($jakarta));
        $this->assertSame($jakarta->getDistance($surabaya), $surabaya->getDistance($jakarta), 'simetris');
    }

    public function testEllipsoidDerivedValues() {
        $wgs = CGeo_Location_Ellipsoid::createDefault();
        $this->assertEqualsWithDelta(6356752.3142, $wgs->getB(), 0.01, 'sumbu minor');
        $this->assertEqualsWithDelta(6371008.77, $wgs->getArithmeticMeanRadius(), 0.5);
        $custom = CGeo_Location_Ellipsoid::createFromArray(['name' => 'Uji', 'a' => 1000, 'f' => 2]);
        $this->assertSame('Uji', $custom->getName());
        $this->assertSame(1000.0, (float) $custom->getA());
        $this->assertEqualsWithDelta(500, $custom->getB(), 0.0001, 'f = pipih terbalik: b = a(1 - 1/f)');
        $this->assertEqualsWithDelta(1000 * (1 - 1 / 2 / 3), $custom->getArithmeticMeanRadius(), 0.0001);
    }

    public function testBoundsCoordinatesAndAddressBuilder() {
        $bounds = new CGeo_Model_Bounds(-8, 105, -5, 115);
        $this->assertSame(['south' => -8.0, 'west' => 105.0, 'north' => -5.0, 'east' => 115.0], $bounds->toArray());
        $coordinates = new CGeo_Model_Coordinates(-6.2, 106.8);
        $this->assertSame([106.8, -6.2], $coordinates->toArray(), 'tuple GeoJSON: longitude dulu');

        $builder = new CGeo_Model_AddressBuilder('uji');
        $address = $builder->setCoordinates(-6.2, 106.8)
            ->setBounds(-8, 105, -5, 115)
            ->setStreetNumber('12')
            ->setStreetName('Jl. Sudirman')
            ->setLocality('Jakarta')
            ->setPostalCode('10220')
            ->setSubLocality('Tanah Abang')
            ->addAdminLevel(1, 'DKI Jakarta', 'JK')
            ->setCountry('Indonesia')
            ->setCountryCode('id')
            ->setTimezone('Asia/Jakarta')
            ->build();
        $this->assertInstanceOf(CGeo_Model_Address::class, $address);
        $this->assertSame('uji', $address->getProvidedBy());
        $this->assertSame(-6.2, $address->getCoordinates()->getLatitude());
        $this->assertSame('Jakarta', $address->getLocality());
        $this->assertSame('Indonesia', $address->getCountry()->getName());
        $this->assertSame('id', $address->getCountry()->getCode(), 'builder menyimpan kode apa adanya; normalisasi huruf besar ada di provider (lihat Nominatim)');
        $this->assertSame('DKI Jakarta', $address->getAdminLevels()->get(1)->getName());
        $this->assertSame('Asia/Jakarta', $address->getTimezone());
        $array = $address->toArray();
        $this->assertSame('Jl. Sudirman', $array['streetName']);
        $this->assertSame(-8.0, $array['bounds']['south']);
    }

    public function testAddressCollectionHelpers() {
        $a = (new CGeo_Model_AddressBuilder('uji'))->setLocality('A')->build();
        $b = (new CGeo_Model_AddressBuilder('uji'))->setLocality('B')->build();
        $collection = new CGeo_Model_AddressCollection([$a, $b]);
        $this->assertCount(2, $collection);
        $this->assertSame('A', $collection->first()->getLocality());
        $this->assertTrue($collection->has(1));
        $this->assertSame('B', $collection->get(1)->getLocality());
        $this->assertCount(1, $collection->slice(1));
        $this->assertFalse($collection->isEmpty());
        $this->assertTrue((new CGeo_Model_AddressCollection())->isEmpty());
        $this->expectException(CGeo_Exception_CollectionIsEmpty::class);
        (new CGeo_Model_AddressCollection())->first();
    }

    public function testGeocodeQueryIsImmutable() {
        $query = CGeo_Query_GeocodeQuery::create('Jakarta')->withLimit(3)->withLocale('id')->withData('countrycodes', 'id');
        $this->assertSame('Jakarta', $query->getText());
        $this->assertSame(3, $query->getLimit());
        $this->assertSame('id', $query->getLocale());
        $this->assertSame('id', $query->getData('countrycodes'));
        $other = $query->withText('Bandung');
        $this->assertSame('Jakarta', $query->getText(), 'with* mengembalikan salinan');
        $this->assertSame('Bandung', $other->getText());
        $reverse = CGeo_Query_ReverseQuery::fromCoordinates(-6.2, 106.8)->withLimit(1);
        $this->assertSame(-6.2, $reverse->getCoordinates()->getLatitude());
        $this->assertStringContainsString('-6.2', (string) $reverse);
    }

    public function testSpatialPointWktGeoJsonAndSrid() {
        $point = CGeo::spatial()->point(-6.2, 106.8);
        $this->assertInstanceOf(CGeo_Spatial_Type_Point::class, $point);
        $this->assertSame('POINT(106.8 -6.2)', $point->toWkt(), 'WKT: longitude dulu');
        $this->assertSame([106.8, -6.2], $point->getCoordinates());
        $json = json_decode($point->toJson(), true);
        $this->assertSame('Point', $json['type']);
        $this->assertSame([106.8, -6.2], $json['coordinates']);
        $this->assertSame(0, $point->srid);

        $fromJson = CGeo_Spatial::point('{"type":"Point","coordinates":[112.75,-7.25]}');
        $this->assertSame('POINT(112.75 -7.25)', $fromJson->toWkt());

        $wgs = new CGeo_Spatial_Type_Point(1.5, 2.5, CGeo_Spatial_Srid::WGS84);
        $this->assertSame(4326, $wgs->srid);
        $this->assertSame(3857, CGeo_Spatial_Srid::WEB_MERCATOR);
    }

    public function testSpatialPolygonAndLineStringRoundTrip() {
        $geojson = '{"type":"Polygon","coordinates":[[[106.8,-6.2],[106.9,-6.2],[106.9,-6.3],[106.8,-6.3],[106.8,-6.2]]]}';
        $polygon = CGeo::spatial()->polygon($geojson);
        $this->assertInstanceOf(CGeo_Spatial_Type_Polygon::class, $polygon);
        $this->assertSame('POLYGON((106.8 -6.2, 106.9 -6.2, 106.9 -6.3, 106.8 -6.3, 106.8 -6.2))', $polygon->toWkt());
        $this->assertSame(json_decode($geojson, true), json_decode($polygon->toJson(), true));
        $again = CGeo_Spatial_Type_Geometry::fromWkt($polygon->toWkt());
        $this->assertInstanceOf(CGeo_Spatial_Type_Polygon::class, $again);
        $this->assertSame($polygon->toWkt(), $again->toWkt());

        $line = CGeo::spatial()->lineString('{"type":"LineString","coordinates":[[0,0],[1,1]]}');
        $this->assertSame('LINESTRING(0 0, 1 1)', $line->toWkt());
        $this->assertCount(2, $line->getGeometries());
        $this->assertSame('POINT(1 1)', $line[1]->toWkt(), 'ArrayAccess ke titik anggota');
        $this->assertSame('LINESTRING(0 0, 1 1)', (string) $line);
    }

    public function testSpatialWkbRoundTrip() {
        $point = new CGeo_Spatial_Type_Point(-6.2, 106.8, CGeo_Spatial_Srid::WGS84);
        $wkb = $point->toWkb();
        $this->assertIsString($wkb);
        $back = CGeo_Spatial_Type_Geometry::fromWkb($wkb);
        $this->assertSame($point->toWkt(), $back->toWkt());
        $this->assertSame(4326, $back->srid, 'SRID ikut tersimpan di WKB (format MySQL: 4 byte SRID + WKB)');
    }

    public function testNominatimGeocodeBuildsUrlAndMapsResponse() {
        $body = json_encode([[
            'place_id' => 1,
            'licence' => 'ODbL',
            'osm_type' => 'relation',
            'osm_id' => '1642911',
            'boundingbox' => ['-6.37', '-5.18', '106.68', '106.97'],
            'lat' => '-6.1753942',
            'lon' => '106.827183',
            'display_name' => 'Jakarta, Indonesia',
            'category' => 'boundary',
            'type' => 'administrative',
            'address' => ['city' => 'Jakarta', 'state' => 'Jawa', 'postcode' => '10110;10120', 'country' => 'Indonesia', 'country_code' => 'id', 'road' => 'Jl. Medan Merdeka'],
            'extratags' => ['population' => '10'],
        ]]);
        $client = new UjiGeo_FakeHttpClient($body);
        $provider = new CGeo_Provider_Nominatim($client, 'https://nominatim.example', 'UjiAgent/1.0', '', 'https://cf.test');
        $this->assertSame('nominatim', $provider->getName());

        $result = $provider->geocodeQuery(CGeo_Query_GeocodeQuery::create('Jakarta')->withLimit(2)->withLocale('id')->withData('countrycodes', ['ID', 'my']));
        $this->assertCount(1, $client->requests);
        $uri = (string) $client->requests[0]->getUri();
        $this->assertStringStartsWith('https://nominatim.example/search?format=jsonv2&q=Jakarta&addressdetails=1&extratags=1&limit=2', $uri);
        $this->assertStringContainsString('countrycodes=id%2Cmy', $uri);
        $this->assertStringContainsString('accept-language=id', $uri);
        $this->assertSame('UjiAgent/1.0', $client->requests[0]->getHeaderLine('User-Agent'));
        $this->assertSame('https://cf.test', $client->requests[0]->getHeaderLine('Referer'));

        $this->assertCount(1, $result);
        $address = $result->first();
        $this->assertInstanceOf(CGeo_Provider_Nominatim_Model_NominatimAddress::class, $address);
        $this->assertSame('Jakarta', $address->getLocality());
        $this->assertSame('10110', $address->getPostalCode(), 'kode pos pertama bila banyak');
        $this->assertSame('ID', $address->getCountry()->getCode());
        $this->assertSame('Jl. Medan Merdeka', $address->getStreetName());
        $this->assertSame(-6.1753942, $address->getCoordinates()->getLatitude());
        $this->assertSame(-6.37, $address->getBounds()->getSouth());
        $this->assertSame('Jawa', $address->getAdminLevels()->get(1)->getName());
        $this->assertSame('Jakarta, Indonesia', $address->getDisplayName());
        $this->assertSame(1642911, $address->getOSMId());
        $this->assertSame('boundary', $address->getCategory());
        $this->assertSame(['population' => '10'], $address->getTags());
    }

    public function testNominatimEmptyAndErrorResponses() {
        $provider = new CGeo_Provider_Nominatim(new UjiGeo_FakeHttpClient('[]'), 'https://n.example', 'ua');
        $this->assertTrue($provider->geocodeQuery(CGeo_Query_GeocodeQuery::create('tempat-tidak-ada'))->isEmpty());

        $provider = new CGeo_Provider_Nominatim(new UjiGeo_FakeHttpClient('bukan json'), 'https://n.example', 'ua');
        try {
            $provider->geocodeQuery(CGeo_Query_GeocodeQuery::create('x'));
            $this->fail('body bukan JSON harus melempar');
        } catch (CGeo_Exception_InvalidServerResponse $e) {
            $this->assertStringContainsString('https://n.example/search', $e->getMessage());
        }

        $provider = new CGeo_Provider_Nominatim(new UjiGeo_FakeHttpClient('{}', 429), 'https://n.example', 'ua');
        try {
            $provider->geocodeQuery(CGeo_Query_GeocodeQuery::create('x'));
            $this->fail('429 harus jadi QuotaExceeded');
        } catch (CGeo_Exception_QuotaExceeded $e) {
            $this->assertTrue(true);
        }

        $provider = new CGeo_Provider_Nominatim(new UjiGeo_FakeHttpClient('{}', 403), 'https://n.example', 'ua');
        $this->expectException(CGeo_Exception_InvalidCredentials::class);
        $provider->geocodeQuery(CGeo_Query_GeocodeQuery::create('x'));
    }

    public function testNominatimRejectsIpAddresses() {
        $provider = new CGeo_Provider_Nominatim(new UjiGeo_FakeHttpClient('[]'), 'https://n.example', 'ua');
        $this->expectException(CGeo_Exception_UnsupportedOperation::class);
        $provider->geocodeQuery(CGeo_Query_GeocodeQuery::create('8.8.8.8'));
    }

    public function testNominatimReverseQuery() {
        $body = json_encode([
            'place_id' => 2, 'licence' => 'ODbL', 'lat' => '-7.25', 'lon' => '112.75', 'display_name' => 'Surabaya',
            'boundingbox' => ['-7.3', '-7.2', '112.7', '112.8'],
            'address' => ['city' => 'Surabaya', 'country' => 'Indonesia', 'country_code' => 'id'],
        ]);
        $client = new UjiGeo_FakeHttpClient($body);
        $provider = new CGeo_Provider_Nominatim($client, 'https://n.example', 'ua');
        $result = $provider->reverseQuery(CGeo_Query_ReverseQuery::fromCoordinates(-7.25, 112.75));
        $this->assertStringContainsString('/reverse?', (string) $client->requests[0]->getUri());
        $this->assertStringContainsString('lat=-7.25', (string) $client->requests[0]->getUri());
        $this->assertSame('Surabaya', $result->first()->getLocality());
    }

    public function testGeoIp2ProviderResolvesFromTheBundledCountryDatabase() {
        $databaseFile = SYSPATH . 'data/GeoLite2/Country.mmdb';
        if (!file_exists($databaseFile)) {
            $this->markTestSkipped('Country.mmdb tidak ada');
        }
        $location = CGeo::ip()->getLocation('8.8.8.8');
        $this->assertInstanceOf(CGeo_Model_AddressCollection::class, $location);
        $this->assertSame('US', $location->first()->getCountry()->getCode());
        $this->assertSame('geoip2', $location->first()->getProvidedBy());
    }

    public function testGeoIpRejectsPrivateAndInvalidAddresses() {
        foreach (['127.0.0.1', '10.0.0.5', '192.168.1.1', 'bukan-ip', ''] as $ip) {
            try {
                CGeo::ip()->getLocation($ip === '' ? '0' : $ip);
                $this->fail($ip . ' harus ditolak');
            } catch (CGeo_Exception_InvalidArgument $e) {
                $this->assertStringContainsString('Invalid IP Address', $e->getMessage());
            }
        }
    }

    public function testClientIpIsReadFromForwardingHeadersAndSkipsPrivateOnes() {
        $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR', 'HTTP_X_CLUSTER_CLIENT_IP'];
        $backup = [];
        foreach ($keys as $key) {
            $backup[$key] = getenv($key);
            putenv($key);
        }
        try {
            $this->assertSame('127.0.0.0', CGeo::ip()->getClientIP(), 'tanpa header → placeholder lokal');
            putenv('HTTP_X_FORWARDED_FOR=10.0.0.1, 203.0.114.9');
            $this->assertSame('203.0.114.9', CGeo::ip()->getClientIP(), 'IP privat di depan dilewati');
            putenv('HTTP_X_FORWARDED_FOR');
            putenv('REMOTE_ADDR=8.8.4.4');
            $this->assertSame('8.8.4.4', CGeo::ip()->getClientIP());
        } finally {
            foreach ($backup as $key => $value) {
                $value === false ? putenv($key) : putenv($key . '=' . $value);
            }
        }
    }

    public function testAssertHelpers() {
        CGeo_Assert::latitude(-6.2);
        CGeo_Assert::longitude(179.9);
        CGeo_Assert::notNull('x');
        $this->expectException(InvalidArgumentException::class);
        CGeo_Assert::latitude(95);
    }
}
