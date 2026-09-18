<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ApiTestSupport.php';

/**
 * CApi_HTTP_Response + formatter JSON + CApi_HTTP_Parser_Accept: konversi isi ke JSON (array,
 * Arrayable, model, koleksi), opsi pretty print/indent, formatter kustom, dan parsing Accept.
 */
class ApiResponseTest extends TestCase {
    protected function setUp(): void {
        ApiTestSupport::registerGroup();
    }

    /**
     * @param mixed $content
     * @param int   $status
     *
     * @return CApi_HTTP_Response
     */
    private function response($content, $status = 200) {
        return (new CApi_HTTP_Response($content, $status))->setGroup(ApiTestSupport::GROUP);
    }

    public function testMorphEncodesAnArrayAsJson() {
        $response = $this->response(['a' => 1, 'b' => [true, null]])->morph();

        $this->assertSame('{"a":1,"b":[true,null]}', $response->getContent());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame(['a' => 1, 'b' => [true, null]], $response->getOriginalContent());
    }

    public function testMorphConvertsArrayablesRecursively() {
        $inner = new CBase_Fluent(['x' => 1]);
        $response = $this->response(['outer' => $inner, 'list' => c::collect([1, 2])])->morph();

        $this->assertSame('{"outer":{"x":1},"list":[1,2]}', $response->getContent());

        $response = $this->response(c::collect(['k' => 'v']))->morph();
        $this->assertSame('{"k":"v"}', $response->getContent(), 'Arrayable di tingkat atas juga');
    }

    public function testMorphLeavesAStringBodyAlone() {
        $response = $this->response('teks polos');
        $response->headers->set('Content-Type', 'text/plain');
        $response->morph();

        $this->assertSame('teks polos', $response->getContent());
        $this->assertSame('text/plain', $response->headers->get('Content-Type'), 'content-type asal dikembalikan');
    }

    public function testMorphIsRepeatableBecauseItStartsFromTheOriginalContent() {
        $response = $this->response(['n' => 1]);
        $response->morph();
        $response->morph();

        $this->assertSame('{"n":1}', $response->getContent());
    }

    public function testMorphFiresTheMorphingEvents() {
        $seen = [];
        CEvent::dispatcher()->listen(CApi_Event_ResponseIsMorphing::class, function ($event) use (&$seen) {
            $seen[] = ['morphing', $event->content];
        });
        CEvent::dispatcher()->listen(CApi_Event_ResponseWasMorphed::class, function ($event) use (&$seen) {
            $seen[] = ['morphed', $event->content];
        });

        try {
            $this->response(['n' => 1])->morph();
        } finally {
            CEvent::dispatcher()->forget(CApi_Event_ResponseIsMorphing::class);
            CEvent::dispatcher()->forget(CApi_Event_ResponseWasMorphed::class);
        }

        $this->assertSame([['morphing', ['n' => 1]], ['morphed', ['n' => 1]]], $seen);
    }

    public function testFormattersCanBeAddedAndLookedUp() {
        $response = $this->response([]);

        $this->assertTrue($response->hasFormatter('json'));
        $this->assertTrue($response->hasFormatter('jsonp'));
        $this->assertTrue($response->hasFormatter('default'));
        $this->assertFalse($response->hasFormatter('xml'));
        $this->assertSame(CApi_HTTP_Response_Format_JsonFormat::class, $response->getFormatter('json'));

        $response->addFormatter('xml', 'MyXmlFormat');
        $this->assertSame('MyXmlFormat', $response->getFormatter('xml'));
        $response->addFormatters(['csv' => 'MyCsvFormat']);
        $this->assertTrue($response->hasFormatter('csv'));

        $this->expectException(Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException::class);
        $response->getFormatter('yaml');
    }

    public function testFormatOptionsPerFormat() {
        $response = $this->response([]);

        $this->assertSame(['pretty_print' => false, 'indent_style' => 'space', 'indent_size' => 2], $response->getFormatsOptions('json'));
        $this->assertTrue($response->hasOptionsForFormat('json'));
        $this->assertFalse($response->hasOptionsForFormat('jsonp'));
        $this->assertSame([], $response->getFormatsOptions('jsonp'));

        $response->addFormatsOptions(['json' => ['pretty_print' => true]]);
        $this->assertSame(['pretty_print' => true], $response->getFormatsOptions('json'));
    }

    public function testJsonFormatPrettyPrintWithSpacesAndTabs() {
        $format = new CApi_HTTP_Response_Format_JsonFormat();
        $this->assertSame('application/json', $format->getContentType());

        $format->setOptions(['pretty_print' => false]);
        $this->assertSame('{"a":{"b":1}}', $format->formatArray(['a' => ['b' => 1]]));

        $format->setOptions(['pretty_print' => true, 'indent_style' => 'space', 'indent_size' => 2]);
        $this->assertSame("{\n  \"a\": {\n    \"b\": 1\n  }\n}", $format->formatArray(['a' => ['b' => 1]]));

        $format->setOptions(['pretty_print' => true, 'indent_style' => 'tab']);
        $this->assertSame("{\n\t\"a\": {\n\t\t\"b\": 1\n\t}\n}", $format->formatArray(['a' => ['b' => 1]]));

        $format->setOptions(['pretty_print' => true, 'indent_style' => 'space', 'indent_size' => 4]);
        $this->assertSame("{\n    \"a\": 1\n}", $format->formatArray(['a' => 1]));
    }

    public function testJsonFormatKeepsUnicodeReadableOnlyWhenPrettyPrinting() {
        $format = new CApi_HTTP_Response_Format_JsonFormat();

        $format->setOptions([]);
        $this->assertSame(json_encode(['kota' => 'Österreich']), $format->formatArray(['kota' => 'Österreich']), 'tanpa pretty print unicode di-escape seperti json_encode bawaan');
        $this->assertStringContainsString('\\u00d6', $format->formatArray(['kota' => 'Österreich']));

        $format->setOptions(['pretty_print' => true]);
        $this->assertStringContainsString('"kota": "Österreich"', $format->formatArray(['kota' => 'Österreich']));
    }

    public function testJsonpFormatWrapsWhenACallbackIsPresent() {
        $format = new CApi_HTTP_Response_Format_JsonpFormat('cb');
        $format->setOptions([]);

        $format->setRequest(CHTTP_Request::create('/', 'GET', ['cb' => 'handle']));
        $this->assertSame('application/javascript', $format->getContentType());
        $this->assertSame('handle({"a":1});', $format->formatArray(['a' => 1]));

        $format->setRequest(CHTTP_Request::create('/', 'GET'));
        $this->assertSame('application/json', $format->getContentType());
        $this->assertSame('{"a":1}', $format->formatArray(['a' => 1]));
    }

    public function testDefaultFormatWrapsTheContentInTheEnvelope() {
        $format = new CApi_HTTP_Response_Format_DefaultFormat();
        $format->setOptions([]);
        $format->setRequest(CHTTP_Request::create('/', 'GET'));

        $this->assertSame('{"errCode":0,"errMessage":"","data":{"a":1}}', $format->formatArray(['a' => 1]));
    }

    public function testMakeFromExistingAndFromJsonKeepStatusAndHeaders() {
        $plain = new CHTTP_Response(['x' => 1], 201, ['X-A' => 'b']);
        $response = CApi_HTTP_Response::makeFromExisting($plain);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('b', $response->headers->get('X-A'));
        $this->assertSame(['x' => 1], $response->getOriginalContent());

        $json = new CHTTP_JsonResponse(['y' => [1, 2]], 202, ['X-J' => 'k']);
        $response = CApi_HTTP_Response::makeFromJson($json);
        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('k', $response->headers->get('X-J'));
        $this->assertSame(['y' => [1, 2]], $response->getOriginalContent(), 'JSON didekode lagi supaya bisa di-morph');
    }

    public function testFluentHelpers() {
        $response = $this->response(['a' => 1]);

        $this->assertSame($response, $response->statusCode(204));
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame($response, $response->withHeader('X-One', '1'));
        $this->assertSame('1', $response->headers->get('X-One'));
        $this->assertSame($response, $response->cookie(new Symfony\Component\HttpFoundation\Cookie('c', 'v')));
        $this->assertCount(1, $response->headers->getCookies());
    }

    public function testMetaGoesThroughTheTransformerBinding() {
        $binding = new CApi_Transformer_Binding(function () {
        });
        $response = new CApi_HTTP_Response([], 200, [], $binding);

        $this->assertSame($response, $response->addMeta('page', 1));
        $this->assertSame($response, $response->meta('total', 10));
        $this->assertSame(['page' => 1, 'total' => 10], $response->getMeta());
        $this->assertSame($response, $response->setMeta(['only' => true]));
        $this->assertSame(['only' => true], $binding->getMeta());
    }

    public function testAcceptParserReadsVendorMediaTypeOrFallsBackToDefaults() {
        $parser = new CApi_HTTP_Parser_Accept('x', 'cf', 'v1', 'json');

        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/x.cf.v2+xml']);
        $this->assertSame(['subtype' => 'cf', 'version' => 'v2', 'format' => 'xml'], $parser->parse($request));

        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html']);
        $this->assertSame(['subtype' => 'cf', 'version' => 'v1', 'format' => 'json'], $parser->parse($request));

        $this->expectException(Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
        $parser->parse($request, true);
    }

    public function testAcceptParserAllowsDottedVersions() {
        $parser = new CApi_HTTP_Parser_Accept('vnd', 'api', 'v1', 'json');
        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/vnd.api.v1.2-beta+json']);

        $this->assertSame(['subtype' => 'api', 'version' => 'v1.2-beta', 'format' => 'json'], $parser->parse($request));
    }

    public function testApiRequestExposesVersionFormatAndSubtypeFromTheGroupParser() {
        $request = CApi_HTTP_Request::createFromBaseHttp(CHTTP_Request::create('/x', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/x..v3+jsonp']));
        $request->setGroup(ApiTestSupport::GROUP);

        $this->assertSame(ApiTestSupport::GROUP, $request->group());
        $this->assertSame('v3', $request->version());
        $this->assertSame('jsonp', $request->format());
        $this->assertSame('', $request->subtype());

        $request = CApi_HTTP_Request::createFromBaseHttp(CHTTP_Request::create('/x', 'GET'));
        $request->setGroup(ApiTestSupport::GROUP);
        $this->assertSame('v1', $request->version(), 'default_format/version grup');
        $this->assertSame('json', $request->format());
    }

    public function testApiRequestCarriesHeadersSessionAndApiData() {
        $base = CHTTP_Request::create('/x', 'POST', ['a' => 1], [], [], ['HTTP_X_CUSTOM' => 'server']);
        $base->headers->set('X-Direct', 'langsung');

        $request = CApi_HTTP_Request::createFromBaseHttp($base);
        $this->assertSame('server', $request->header('X-Custom'));
        $this->assertSame('langsung', $request->header('X-Direct'), 'header yang diset langsung ikut tersalin');
        $this->assertSame(1, $request->input('a'));

        $this->assertSame($request, $request->setApiData('user', ['id' => 5]));
        $this->assertSame(['id' => 5], $request->getApiData('user'));
        $this->assertSame('bawaan', $request->getApiData('missing', 'bawaan'));
        $this->assertSame('dari-closure', $request->getApiData('missing', function () {
            return 'dari-closure';
        }));

        $this->assertInstanceOf(CSession_Store::class, $request->session());
        $fake = new stdClass();
        $request->setSessionResolver(function () use ($fake) {
            return $fake;
        });
        $this->assertSame($fake, $request->session());
    }
}
