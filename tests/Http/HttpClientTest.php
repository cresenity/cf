<?php

use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response as Psr7Response;

/**
 * CHTTP_Client - padanan suite hulu untuk klien HTTP: semua request dipalsukan lewat fake(),
 * tidak ada lalu lintas jaringan.
 */
class HttpClientTest extends TestCase {
    /**
     * @var CHTTP_Client
     */
    protected $factory;

    protected function setUp(): void {
        //request yang lolos dari fake harus gagal keras, bukan diam-diam ke jaringan
        $this->factory = (new CHTTP_Client())->preventStrayRequests();
        CHTTP_Client_Exception_RequestException::truncate();
    }

    /**
     * @param callable $callback
     * @param string   $message
     */
    private function assertAssertionFails(callable $callback, $message = '') {
        //expectException() tidak bisa dipakai: kelas exception PHPUnit yang di-vendor tidak
        //ter-autoload lewat PSR-0, tetapi catch tetap cocok begitu PHPUnit sendiri melemparnya
        try {
            $callback();
        } catch (Throwable $e) {
            //PHPUnit yang di-vendor memindahkan kelasnya ke sub-namespace Exception; dukung keduanya
            foreach (['PHPUnit\Framework\AssertionFailedError', 'PHPUnit\Framework\Exception\AssertionFailedError'] as $class) {
                if ($e instanceof $class) {
                    $this->assertTrue(true);

                    return;
                }
            }

            throw $e;
        }

        $this->fail($message ?: 'assertion seharusnya gagal');
    }

    public function testStubbedResponsesAreReturnedAfterFaking() {
        $this->factory->fake();

        $response = $this->factory->post('http://example.com/test-missing-page');

        $this->assertTrue($response->ok());
        $this->assertSame(200, $response->status());
        $this->assertSame('', $response->body());
    }

    public function testResponsesCanBeStubbedPerHost() {
        $this->factory->fake([
            'vapor.example.com' => CHTTP_Client::response('', 201),
            'forge.example.com' => CHTTP_Client::response('', 200),
        ]);

        $this->assertTrue($this->factory->post('http://vapor.example.com')->created());
        $this->assertFalse($this->factory->post('http://forge.example.com')->created());
    }

    public function testStatusCodeShorthand() {
        $this->factory->fake([
            'forge.example.com' => 204,
            'vapor.example.com' => 201,
        ]);

        $this->assertTrue($this->factory->post('http://forge.example.com')->noContent());
        $this->assertTrue($this->factory->post('http://vapor.example.com')->created());
    }

    public function testBodyShorthands() {
        $this->factory->fake([
            'google.com' => 'Hello World',
            'github.com' => ['foo' => 'bar'],
        ]);

        $response = $this->factory->get('http://google.com');
        $this->assertTrue($response->ok());
        $this->assertSame('Hello World', $response->body());

        $response = $this->factory->post('http://github.com');
        $this->assertTrue($response->ok());
        $this->assertSame('{"foo":"bar"}', $response->body());
        $this->assertSame(['foo' => 'bar'], $response->json());
        $this->assertSame('application/json', $response->header('Content-Type'));
    }

    /**
     * @return array
     */
    public function statusHelperProvider() {
        return [
            [202, 'accepted'],
            [301, 'movedPermanently'],
            [204, 'noContent'],
            [302, 'found'],
            [304, 'notModified'],
            [400, 'badRequest'],
            [402, 'paymentRequired'],
            [408, 'requestTimeout'],
            [409, 'conflict'],
            [422, 'unprocessableEntity'],
            [429, 'tooManyRequests'],
            [401, 'unauthorized'],
            [403, 'forbidden'],
            [404, 'notFound'],
        ];
    }

    /**
     * @dataProvider statusHelperProvider
     *
     * @param int    $status
     * @param string $method
     */
    public function testStatusHelpers($status, $method) {
        $this->factory->fake([
            'match.example.com' => CHTTP_Client::response('', $status),
            'other.example.com' => CHTTP_Client::response('', 200),
        ]);

        $this->assertTrue($this->factory->post('http://match.example.com')->{$method}());
        $this->assertFalse($this->factory->post('http://other.example.com')->{$method}());
    }

    public function testStatusFamilies() {
        $this->factory->fake([
            '200.com' => 200,
            '302.com' => 302,
            '404.com' => 404,
            '500.com' => 500,
        ]);

        $ok = $this->factory->get('http://200.com');
        $this->assertTrue($ok->successful());
        $this->assertFalse($ok->failed());
        $this->assertFalse($ok->redirect());

        $redirect = $this->factory->get('http://302.com');
        $this->assertTrue($redirect->redirect());
        $this->assertFalse($redirect->successful());

        $notFound = $this->factory->get('http://404.com');
        $this->assertTrue($notFound->failed());
        $this->assertTrue($notFound->clientError());
        $this->assertFalse($notFound->serverError());

        $error = $this->factory->get('http://500.com');
        $this->assertTrue($error->failed());
        $this->assertTrue($error->serverError());
        $this->assertFalse($error->clientError());
    }

    public function testResponseBodyCasting() {
        $this->factory->fake([
            '*' => ['result' => ['foo' => 'bar']],
        ]);

        $response = $this->factory->get('http://foo.com/api');

        $this->assertSame('{"result":{"foo":"bar"}}', $response->body());
        $this->assertSame('{"result":{"foo":"bar"}}', (string) $response);
        $this->assertIsArray($response->json());
        $this->assertSame(['foo' => 'bar'], $response->json()['result']);
        $this->assertSame(['foo' => 'bar'], $response->json('result'));
        $this->assertSame('bar', $response->json('result.foo'));
        $this->assertSame('default', $response->json('missing_key', 'default'));
        $this->assertSame(['foo' => 'bar'], $response['result']);
        $this->assertTrue(isset($response['result']));
        $this->assertFalse(isset($response['missing']));
    }

    public function testResponseObjectAsArrayAndObject() {
        $this->factory->fake([
            'list.com/*' => [['foo' => 'bar'], ['bar' => 'foo']],
            'obj.com/*' => ['result' => ['foo' => 'bar']],
        ]);

        $response = $this->factory->get('http://list.com/api');
        $this->assertSame('[{"foo":"bar"},{"bar":"foo"}]', $response->body());
        $this->assertIsArray($response->object());
        $this->assertSame('bar', $response->object()[0]->foo);

        $response = $this->factory->get('http://obj.com/api');
        $this->assertIsObject($response->object());
        $this->assertSame('bar', $response->object()->result->foo);
    }

    public function testResponseObjectIsMacroable() {
        CHTTP_Client_Response::macro('movieFields', function () {
            return $this->collect()->mapWithKeys(function ($field, $key) {
                return [strtolower($key) => $field];
            })->toArray();
        });
        $this->factory->fake([
            '*' => ['Title' => 'The Godfather', 'Year' => 1972],
        ]);

        $response = $this->factory->get('http://www.omdbapi.com/?apikey=test_api_key&i=test_imdb_id');

        $this->assertSame(['title' => 'The Godfather', 'year' => 1972], $response->movieFields());
    }

    public function testResponseCanBeReturnedAsResourceCollectionAndFluent() {
        $this->factory->fake([
            '*' => ['result' => ['foo' => 'bar']],
        ]);

        $response = $this->factory->get('http://foo.com/api');

        $this->assertIsResource($response->resource());
        $this->assertSame('{"result":{"foo":"bar"}}', stream_get_contents($response->resource()));

        $this->assertInstanceOf(CCollection::class, $response->collect());
        $this->assertEquals(c::collect(['result' => ['foo' => 'bar']]), $response->collect());
        $this->assertEquals(c::collect(['foo' => 'bar']), $response->collect('result'));
        $this->assertEquals(c::collect(['bar']), $response->collect('result.foo'));
        $this->assertEquals(c::collect(), $response->collect('missing_key'));

        $this->assertInstanceOf(CBase_Fluent::class, $response->fluent());
        $this->assertEquals(new CBase_Fluent(['result' => ['foo' => 'bar']]), $response->fluent());
        $this->assertEquals(new CBase_Fluent(['foo' => 'bar']), $response->fluent('result'));
        $this->assertEquals(new CBase_Fluent([]), $response->fluent('missing_key'));
    }

    public function testSendRequestBodyAsJsonByDefault() {
        $body = '{"test":"phpunit"}';
        $this->factory->fake(function (CHTTP_Client_Request $request) use ($body) {
            $this->assertSame($body, $request->body());
            $this->assertContains('application/json', $request->header('Content-Type'));

            return ['my' => 'response'];
        });

        $response = $this->factory->withBody($body)->send('get', 'http://foo.com/api');
        $this->assertSame(['my' => 'response'], $response->json());
    }

    public function testSendRequestBodyWithManyAmpersands() {
        $body = str_repeat('A thousand &. ', 1000);
        $this->factory->fake(function (CHTTP_Client_Request $request) use ($body) {
            $this->assertSame($body, $request->body());
            $this->assertContains('text/plain', $request->header('Content-Type'));

            return ['my' => 'response'];
        });

        $this->factory->withBody($body, 'text/plain')->send('post', 'http://foo.com/api');
        $this->factory->assertSentCount(1);
    }

    public function testSendStreamRequestBody() {
        $string = 'Look at me, i am a stream!!';
        $resource = fopen('php://temp', 'w');
        fwrite($resource, $string);
        rewind($resource);
        $this->factory->fake(function (CHTTP_Client_Request $request) use ($string) {
            $this->assertSame($string, $request->body());

            return ['my' => 'response'];
        });

        $this->factory->withBody(Utils::streamFor($resource), 'text/plain')->send('post', 'http://foo.com/api');
        $this->factory->assertSentCount(1);
    }

    public function testUrlsCanBeStubbedByPath() {
        $this->factory->fake([
            'foo.com/*' => ['page' => 'foo'],
            'bar.com/*' => ['page' => 'bar'],
            '*' => ['page' => 'fallback'],
        ]);

        $this->assertSame('foo', $this->factory->post('http://foo.com/test')['page']);
        $this->assertSame('bar', $this->factory->post('http://bar.com/test')['page']);
        $this->assertSame('fallback', $this->factory->post('http://fallback.com/test')['page']);

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://foo.com/test'
                && $request->hasHeader('Content-Type', 'application/json');
        });
    }

    public function testCanSendJsonData() {
        $this->factory->fake();

        $this->factory->withHeaders([
            'X-Test-Header' => 'foo',
            'X-Test-ArrayHeader' => ['bar', 'baz'],
        ])->post('http://foo.com/json', ['name' => 'Hery']);

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://foo.com/json'
                && $request->hasHeader('Content-Type', 'application/json')
                && $request->hasHeader('X-Test-Header', 'foo')
                && $request->hasHeader('X-Test-ArrayHeader', ['bar', 'baz'])
                && $request->isJson()
                && $request['name'] === 'Hery'
                && $request->data() === ['name' => 'Hery'];
        });
    }

    public function testCanSendFormData() {
        $this->factory->fake();

        $this->factory->asForm()->post('http://foo.com/form', [
            'name' => 'Hery',
            'title' => 'Developer',
        ]);

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://foo.com/form'
                && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
                && $request->isForm()
                && $request['name'] === 'Hery'
                && $request->body() === 'name=Hery&title=Developer';
        });
    }

    public function testRequestHeadersAreCheckedCaseInsensitively() {
        $this->factory->fake();

        $this->factory->withHeaders(['foo' => 'Bar'])->post('http://foo.com/json');

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->hasHeader('Foo')
                && $request->hasHeader('Foo', 'Bar')
                && $request->header('Foo') === ['Bar'];
        });
    }

    public function testContentTypeHeadersAreCheckedCaseInsensitively() {
        $this->factory->fake();

        $this->factory->send('POST', 'http://foo.com/json', [
            'headers' => ['content-type' => 'application/json'],
            'body' => '{"name":"Hery"}',
        ]);

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->hasHeader('Content-Type')
                && $request->header('Content-Type') === ['application/json']
                && $request->isJson();
        });
    }

    public function testRecordedCallsAreEmptiedWhenFakeIsCalled() {
        $this->factory->fake(['http://foo.com/*' => ['page' => 'foo']]);
        $this->factory->get('http://foo.com/test');
        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://foo.com/test';
        });

        $this->factory->fake();

        $this->factory->assertNothingSent();
    }

    public function testAssertNotSentAndNothingSent() {
        $this->factory->fake();
        $this->factory->assertNothingSent();

        $this->factory->post('http://foo.com/form', ['name' => 'Hery']);

        $this->factory->assertNotSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://foo.com/form' && $request['name'] === 'Budi';
        });
    }

    public function testAssertSentFailsWhenNothingMatches() {
        $this->factory->fake();
        $this->factory->get('http://foo.com/a');

        $this->assertAssertionFails(function () {
            $this->factory->assertSent(function (CHTTP_Client_Request $request) {
                return $request->url() === 'http://foo.com/b';
            });
        });
    }

    public function testRequestCount() {
        $this->factory->fake();
        $this->factory->assertSentCount(0);

        $this->factory->post('http://foo.com/form', ['name' => 'Hery']);
        $this->factory->assertSentCount(1);

        $this->factory->post('http://foo.com/form', ['name' => 'Budi']);
        $this->factory->assertSentCount(2);
        $this->assertCount(2, $this->factory->recorded());
        $this->assertCount(1, $this->factory->recorded(function (CHTTP_Client_Request $request) {
            return $request['name'] === 'Budi';
        }));
    }

    public function testCanSendMultipartData() {
        $this->factory->fake();

        $this->factory->asMultipart()->post('http://foo.com/multipart', [
            ['name' => 'foo', 'contents' => 'data', 'headers' => ['X-Test-Header' => 'foo']],
        ]);

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://foo.com/multipart'
                && cstr::startsWith($request->header('Content-Type')[0], 'multipart')
                && $request->isMultipart()
                && $request[0]['name'] === 'foo';
        });
    }

    public function testFilesCanBeAttached() {
        $this->factory->fake();

        $this->factory->attach('foo', 'data', 'file.txt', ['X-Test-Header' => 'foo'])->post('http://foo.com/file');

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://foo.com/file'
                && cstr::startsWith($request->header('Content-Type')[0], 'multipart')
                && $request[0]['name'] === 'foo'
                && $request->hasFile('foo', 'data', 'file.txt');
        });
    }

    public function testItCanSendToken() {
        $this->factory->fake();

        $this->factory->withToken('token')->post('http://foo.com/json');

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->hasHeader('Authorization', 'Bearer token');
        });
    }

    public function testItCanSendBasicAuth() {
        $this->factory->fake();

        $this->factory->withBasicAuth('user', 'pass')->get('http://foo.com/json');

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->hasHeader('Authorization', 'Basic ' . base64_encode('user:pass'));
        });
    }

    public function testItOnlySendsOneUserAgentHeader() {
        $this->factory->fake();

        $this->factory->withUserAgent('Cresenity')->withUserAgent('FooBar')->post('http://foo.com/json');

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            $userAgent = $request->header('User-Agent');

            return count($userAgent) === 1 && $userAgent[0] === 'FooBar';
        });
    }

    public function testAcceptHeaders() {
        $this->factory->fake();

        $this->factory->acceptJson()->get('http://foo.com/a');
        $this->factory->accept('text/html')->get('http://foo.com/b');

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://foo.com/a' && $request->hasHeader('Accept', 'application/json');
        });
        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://foo.com/b' && $request->hasHeader('Accept', 'text/html');
        });
    }

    public function testSequenceBuilder() {
        $this->factory->fake([
            '*' => $this->factory->sequence()
                ->push('Ok', 201)
                ->push(['fact' => 'Cats are great!'])
                ->pushFile(__DIR__ . '/Fixtures/test.txt')
                ->pushStatus(403),
        ]);

        $response = $this->factory->get('https://example.com');
        $this->assertSame('Ok', $response->body());
        $this->assertSame(201, $response->status());

        $response = $this->factory->get('https://example.com');
        $this->assertSame(['fact' => 'Cats are great!'], $response->json());
        $this->assertSame('application/json', $response->header('Content-Type'));
        $this->assertSame(200, $response->status());

        $response = $this->factory->get('https://example.com');
        $this->assertSame(
            "This is a story about something that happened long ago when your grandfather was a child.\n",
            str_replace("\r\n", "\n", $response->body())
        );

        $response = $this->factory->get('https://example.com');
        $this->assertSame('', $response->body());
        $this->assertSame(403, $response->status());

        $this->expectException(OutOfBoundsException::class);
        $this->factory->get('https://example.com');
    }

    public function testSequenceBuilderCanKeepGoingWhenEmpty() {
        $this->factory->fake([
            '*' => $this->factory->sequence()->dontFailWhenEmpty()->push('Ok'),
        ]);

        $this->assertSame('Ok', $this->factory->get('https://example.com')->body());
        //urutan sudah habis: dijawab respons 200 kosong, bukan gagal
        $response = $this->factory->get('https://example.com');
        $this->assertSame(200, $response->status());
        $this->assertSame('', $response->body());
    }

    public function testSequenceWhenEmptyUsesTheGivenResponse() {
        $this->factory->fake([
            '*' => $this->factory->sequence()->push('Ok')->whenEmpty(CHTTP_Client::response('Habis', 410)),
        ]);

        $this->assertSame('Ok', $this->factory->get('https://example.com')->body());
        $response = $this->factory->get('https://example.com');
        $this->assertSame('Habis', $response->body());
        $this->assertSame(410, $response->status());
    }

    public function testAssertSequencesAreEmpty() {
        $this->factory->fake([
            '*' => $this->factory->sequence()->push('1')->push('2'),
        ]);

        $this->factory->get('https://example.com');
        $this->factory->get('https://example.com');

        $this->factory->assertSequencesAreEmpty();
    }

    public function testAssertSequencesAreEmptyFailsWhenAResponseRemains() {
        $this->factory->fake([
            '*' => $this->factory->sequence()->push('1')->push('2'),
        ]);
        $this->factory->get('https://example.com');

        $this->assertAssertionFails(function () {
            $this->factory->assertSequencesAreEmpty();
        });
    }

    public function testFakeSequence() {
        $this->factory->fakeSequence()->pushStatus(201)->pushStatus(301);

        $this->assertSame(201, $this->factory->get('https://example.com')->status());
        $this->assertSame(301, $this->factory->get('https://example.com')->status());
    }

    public function testWithCookies() {
        $this->factory->fakeSequence()->pushStatus(200);

        $response = $this->factory->withCookies(['foo' => 'bar'], 'example.com')->get('https://example.com');

        $this->assertCount(1, $response->cookies()->toArray());
        $cookie = $response->cookies()->toArray()[0];
        $this->assertSame('foo', $cookie['Name']);
        $this->assertSame('bar', $cookie['Value']);
        $this->assertSame('example.com', $cookie['Domain']);
    }

    public function testGetWithArrayQueryParam() {
        $this->factory->fake();

        $this->factory->get('http://foo.com/get', ['foo' => 'bar']);

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://foo.com/get?foo=bar' && $request['foo'] === 'bar';
        });
    }

    public function testGetWithStringQueryParam() {
        $this->factory->fake();

        $this->factory->get('http://foo.com/get', 'foo=bar');

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://foo.com/get?foo=bar' && $request['foo'] === 'bar';
        });
    }

    public function testGetWithQueryInTheUrl() {
        $this->factory->fake();

        $this->factory->get('http://foo.com/get?foo=bar&page=1');

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://foo.com/get?foo=bar&page=1'
                && $request['foo'] === 'bar'
                && $request['page'] === '1';
        });
    }

    public function testGetWithQueryWontEncode() {
        $this->factory->fake();

        $this->factory->get('http://foo.com/get?foo;bar;1;5;10&page=1');

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://foo.com/get?foo;bar;1;5;10&page=1'
                && !isset($request['foo'])
                && $request['page'] === '1';
        });
    }

    public function testGetWithArrayQueryParamOverwritesTheUrlQuery() {
        $this->factory->fake();

        $this->factory->get('http://foo.com/get?foo=bar&page=1', ['hello' => 'world']);

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://foo.com/get?hello=world' && $request['hello'] === 'world';
        });
    }

    public function testGetWithArrayQueryParamEncodes() {
        $this->factory->fake();

        $this->factory->get('http://foo.com/get', ['foo;bar; space test' => 'cf']);

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://foo.com/get?foo%3Bbar%3B%20space%20test=cf'
                && $request['foo;bar; space test'] === 'cf';
        });
    }

    public function testWithBaseUrl() {
        $this->factory->fake();
        $this->factory->baseUrl('http://foo.com/')->get('get');
        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://foo.com/get';
        });

        $this->factory->fake();
        $this->factory->baseUrl('http://foo.com/')->get('http://bar.com/get');
        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://bar.com/get';
        });
    }

    public function testCanConfirmManyHeaders() {
        $this->factory->fake();

        $this->factory->withHeaders([
            'X-Test-Header' => 'foo',
            'X-Test-ArrayHeader' => ['bar', 'baz'],
        ])->post('http://foo.com/json');

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->hasHeaders(['X-Test-Header' => 'foo', 'X-Test-ArrayHeader' => ['bar', 'baz']])
                && $request->hasHeaders('X-Test-Header')
                && !$request->hasHeaders(['X-Test-Header' => 'lain']);
        });
    }

    public function testItMergesMultipleHeaders() {
        $this->factory->fake();

        $this->factory->withHeaders(['X-Test-Header' => 'foo'])
            ->withHeaders(['X-Test-ArrayHeader' => ['bar', 'baz']])
            ->post('http://foo.com/json');

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->hasHeaders(['X-Test-Header' => 'foo', 'X-Test-ArrayHeader' => ['bar', 'baz']]);
        });
    }

    public function testItCanReplaceHeaders() {
        $this->factory->fake();

        $this->factory->withHeaders(['X-Test-Header' => 'foo'])
            ->replaceHeaders(['X-Test-Header' => 'baz'])
            ->post('http://foo.com/json');
        $this->factory->replaceHeaders(['X-Only' => 'baz'])->post('http://foo.com/other');

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://foo.com/json' && $request->hasHeaders(['X-Test-Header' => ['baz']]);
        });
        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://foo.com/other' && $request->hasHeaders(['X-Only' => ['baz']]);
        });
    }

    public function testCanConfirmSingleHeader() {
        $this->factory->fake();

        $this->factory->withHeader('X-Test-Header', 'foo')->post('http://foo.com/json');
        $this->factory->withHeader('X-Test-ArrayHeader', ['bar', 'baz'])->post('http://foo.com/array');

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://foo.com/json' && $request->hasHeaders(['X-Test-Header' => 'foo']);
        });
        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://foo.com/array' && $request->hasHeaders(['X-Test-ArrayHeader' => ['bar', 'baz']]);
        });
    }

    public function testExceptionAccessor() {
        $this->assertNull((new CHTTP_Client_Response(new Psr7Response()))->toException());

        $error = ['error' => ['code' => 403, 'message' => 'The Request can not be completed']];
        $response = new CHTTP_Client_Response(new Psr7Response(403, [], json_encode($error)));

        $exception = $response->toException();
        $this->assertInstanceOf(CHTTP_Client_Exception_RequestException::class, $exception);
        $this->assertSame($response, $exception->response);
        $this->assertSame(403, $exception->getCode());
    }

    public function testRequestExceptionSummary() {
        $error = ['error' => ['code' => 403, 'message' => 'The Request can not be completed']];
        $exception = new CHTTP_Client_Exception_RequestException(new CHTTP_Client_Response(new Psr7Response(403, [], json_encode($error))));

        $this->assertSame('HTTP request returned status code 403:' . "\n" . json_encode($error) . "\n", $exception->getMessage());
    }

    public function testRequestExceptionTruncatedSummary() {
        $error = ['error' => ['code' => 403, 'message' => 'The Request can not be completed because quota limit was exceeded. Please, check our support team to increase your limit']];
        $exception = new CHTTP_Client_Exception_RequestException(new CHTTP_Client_Response(new Psr7Response(403, [], json_encode($error))));

        $this->assertStringEndsWith("(truncated...)\n", $exception->getMessage());
        $this->assertStringContainsString('{"error":{"code":403,"message":"The Request can not be completed because quota limit was exceeded. Please, check our sup (truncated...)', $exception->getMessage());
    }

    public function testRequestExceptionWithoutTruncatedSummary() {
        CHTTP_Client_Exception_RequestException::dontTruncate();
        $error = ['error' => ['code' => 403, 'message' => 'The Request can not be completed because quota limit was exceeded. Please, check our support team to increase your limit']];
        $exception = new CHTTP_Client_Exception_RequestException(new CHTTP_Client_Response(new Psr7Response(403, [], json_encode($error))));

        $this->assertStringEndsWith(json_encode($error) . "\n", $exception->getMessage());
    }

    public function testRequestExceptionWithCustomTruncatedSummary() {
        CHTTP_Client_Exception_RequestException::truncateAt(20);
        $exception = new CHTTP_Client_Exception_RequestException(new CHTTP_Client_Response(new Psr7Response(403, [], str_repeat('x', 50))));

        $this->assertStringEndsWith(str_repeat('x', 20) . " (truncated...)\n", $exception->getMessage());
    }

    public function testRequestExceptionEmptyBody() {
        $exception = new CHTTP_Client_Exception_RequestException(new CHTTP_Client_Response(new Psr7Response(403)));

        $this->assertSame('HTTP request returned status code 403', $exception->getMessage());
    }

    /**
     * @return array
     */
    public function onErrorProvider() {
        return [
            [101, false],
            [201, false],
            [301, false],
            [401, true],
            [500, true],
        ];
    }

    /**
     * @dataProvider onErrorProvider
     *
     * @param int  $status
     * @param bool $called
     */
    public function testOnErrorOnlyRunsForClientAndServerErrors($status, $called) {
        $seen = 0;
        $this->factory->fake(['example.com' => CHTTP_Client::response('', $status)]);

        $response = $this->factory->get('example.com')->onError(function ($response) use (&$seen) {
            $seen = $response->status();
        });

        $this->assertSame($called ? $status : 0, $seen);
        $this->assertSame($status, $response->status());
    }

    public function testCanAssertAgainstOrderOfHttpRequestsWithUrlStrings() {
        $this->factory->fake();
        $urls = ['http://example.com/1', 'http://example.com/2', 'http://example.com/3'];
        foreach ($urls as $url) {
            $this->factory->get($url);
        }

        $this->factory->assertSentInOrder($urls);
    }

    public function testAssertionsSentOutOfOrderThrowAssertionFailed() {
        $this->factory->fake();
        $urls = ['http://example.com/1', 'http://example.com/2', 'http://example.com/3'];
        $this->factory->get($urls[0]);
        $this->factory->get($urls[2]);
        $this->factory->get($urls[1]);

        $this->assertAssertionFails(function () use ($urls) {
            $this->factory->assertSentInOrder($urls);
        });
    }

    public function testWrongNumberOfRequestsThrowAssertionFailed() {
        $this->factory->fake();
        $urls = ['http://example.com/1', 'http://example.com/2', 'http://example.com/3'];
        $this->factory->get($urls[0]);
        $this->factory->get($urls[1]);

        $this->assertAssertionFails(function () use ($urls) {
            $this->factory->assertSentInOrder($urls);
        });
    }

    public function testCanAssertAgainstOrderOfHttpRequestsWithCallables() {
        $this->factory->fake();
        $this->factory->withHeader('X-One', '1')->get('http://example.com/1');
        $this->factory->get('http://example.com/2');

        $this->factory->assertSentInOrder([
            function (CHTTP_Client_Request $request) {
                return $request->url() === 'http://example.com/1' && $request->hasHeader('X-One', '1');
            },
            function (CHTTP_Client_Request $request) {
                return $request->url() === 'http://example.com/2';
            },
        ]);
    }

    public function testRequestsCanBeAsync() {
        $this->factory->fake(['*' => CHTTP_Client::response('async', 200)]);

        $request = $this->factory->createPendingRequest()->async();
        $promise = $request->get('http://foo.com');

        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $this->assertSame($promise, $request->getPromise());
        $this->assertSame('async', $promise->wait()->body());
        $this->factory->assertSentCount(1);
    }

    public function testClientCanBeSet() {
        $client = $this->factory->buildClient();
        $request = new CHTTP_Client_PendingRequest($this->factory);

        $this->assertNotSame($client, $request->buildClient());

        $request->setClient($client);
        $this->assertSame($client, $request->buildClient());
    }

    public function testRequestsCanReplaceOptions() {
        $request = new CHTTP_Client_PendingRequest($this->factory);
        $request = $request->withOptions(['http_errors' => true, 'connect_timeout' => 10]);
        $this->assertSame(10, $request->getOptions()['connect_timeout']);
        $this->assertTrue($request->getOptions()['http_errors']);

        $request = $request->withOptions(['connect_timeout' => 20]);
        $this->assertSame(20, $request->getOptions()['connect_timeout']);
        $this->assertTrue($request->getOptions()['http_errors'], 'opsi lain tidak ikut hilang');
    }

    public function testMultipleRequestsAreSentInThePool() {
        $this->factory->fake([
            '200.com' => CHTTP_Client::response('', 200),
            '400.com' => CHTTP_Client::response('', 400),
            '500.com' => CHTTP_Client::response('', 500),
        ]);

        $responses = $this->factory->pool(function (CHTTP_Client_Pool $pool) {
            return [
                $pool->get('200.com'),
                $pool->get('400.com'),
                $pool->get('500.com'),
            ];
        });

        $this->assertSame(200, $responses[0]->status());
        $this->assertSame(400, $responses[1]->status());
        $this->assertSame(500, $responses[2]->status());
    }

    public function testMultipleRequestsAreSentInThePoolWithKeys() {
        $this->factory->fake([
            '200.com' => CHTTP_Client::response('', 200),
            '400.com' => CHTTP_Client::response('', 400),
        ]);

        $responses = $this->factory->pool(function (CHTTP_Client_Pool $pool) {
            return [
                $pool->as('test200')->get('200.com'),
                $pool->as('test400')->get('400.com'),
                $pool->get('200.com'),
            ];
        });

        $this->assertSame(200, $responses['test200']->status());
        $this->assertSame(400, $responses['test400']->status());
        $this->assertSame(200, $responses[0]->status());
    }

    public function testMiddlewareRunsInPool() {
        $this->factory->fake(function (CHTTP_Client_Request $request) {
            return CHTTP_Client::response('Fake');
        });
        $history = [];
        $middleware = Middleware::history($history);

        $responses = $this->factory->pool(function (CHTTP_Client_Pool $pool) use ($middleware) {
            return [
                $pool->withMiddleware($middleware)->post('https://example.com', ['hyped-for' => 'cf']),
            ];
        });

        $this->assertSame('Fake', $responses[0]->body());
        $this->assertCount(1, $history);
        $this->assertSame(['hyped-for' => 'cf'], json_decode(c::tap($history[0]['request']->getBody())->rewind()->getContents(), true));
    }

    public function testTheRequestSendingAndResponseReceivedEventsAreFired() {
        //CHTTP_Client final dan memakai dispatcher global, jadi didengarkan lewat CEvent
        $events = CEvent::dispatcher();
        $sending = [];
        $received = [];
        $events->listen(CHTTP_Client_Event_RequestSending::class, function ($event) use (&$sending) {
            $sending[] = $event->request->url();
        });
        $events->listen(CHTTP_Client_Event_ResponseReceived::class, function ($event) use (&$received) {
            $received[] = $event->response->status();
        });

        try {
            $this->factory->fake(['*' => 201]);
            $this->factory->get('https://example.com/a');
            $this->factory->post('https://example.com/b');
        } finally {
            $events->forget(CHTTP_Client_Event_RequestSending::class);
            $events->forget(CHTTP_Client_Event_ResponseReceived::class);
        }

        $this->assertSame(['https://example.com/a', 'https://example.com/b'], $sending);
        $this->assertSame([201, 201], $received);
    }

    public function testRequestIsMacroable() {
        CHTTP_Client_Request::macro('customMethod', function () {
            return 'yes!';
        });
        $this->factory->fake(function (CHTTP_Client_Request $request) {
            $this->assertSame('yes!', $request->customMethod());

            return CHTTP_Client::response();
        });

        $this->factory->get('https://example.com');
        $this->factory->assertSentCount(1);
    }

    public function testRequestExceptionIsThrownWhenRetriesExhausted() {
        $this->factory->fake(['*' => CHTTP_Client::response(['error'], 403)]);

        $exception = null;

        try {
            $this->factory->retry(2, 0, null, true)->get('http://foo.com/get');
        } catch (CHTTP_Client_Exception_RequestException $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(CHTTP_Client_Exception_RequestException::class, $exception);
        $this->factory->assertSentCount(2);
    }

    public function testRequestExceptionIsThrownWithoutRetriesIfRetryNotNecessary() {
        $this->factory->fake(['*' => CHTTP_Client::response(['error'], 500)]);
        $exception = null;
        $whenAttempts = 0;

        try {
            $this->factory->retry(2, 0, function ($exception) use (&$whenAttempts) {
                $whenAttempts++;

                return $exception->response->status() === 403;
            }, true)->get('http://foo.com/get');
        } catch (CHTTP_Client_Exception_RequestException $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(CHTTP_Client_Exception_RequestException::class, $exception);
        $this->assertSame(1, $whenAttempts);
        $this->factory->assertSentCount(1);
    }

    public function testRequestExceptionIsNotThrownWhenDisabledAndRetriesExhausted() {
        $this->factory->fake(['*' => CHTTP_Client::response(['error'], 403)]);

        $response = $this->factory->retry(2, 0, null, false)->get('http://foo.com/get');

        $this->assertTrue($response->failed());
        $this->factory->assertSentCount(2);
    }

    public function testRetrySucceedsWhenALaterAttemptPasses() {
        $this->factory->fake([
            '*' => $this->factory->sequence()->push(['error'], 500)->push(['ok'], 200),
        ]);

        $response = $this->factory->retry(3, 0)->get('http://foo.com/get');

        $this->assertTrue($response->successful());
        $this->assertSame(['ok'], $response->json());
        $this->factory->assertSentCount(2);
    }

    public function testRequestCanBeModifiedInRetryCallback() {
        $this->factory->fake([
            '*' => $this->factory->sequence()->push(['error'], 500)->push(['ok'], 200),
        ]);

        $response = $this->factory->retry(2, 0, function ($exception, $request) {
            $this->assertInstanceOf(CHTTP_Client_PendingRequest::class, $request);
            $request->withHeaders(['Foo' => 'Bar']);

            return true;
        }, false)->get('http://foo.com/get');

        $this->assertTrue($response->successful());
        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->hasHeader('Foo') && $request->header('Foo') === ['Bar'];
        });
    }

    public function testExceptionThrownInRetryCallbackStopsRetrying() {
        $this->factory->fake(['*' => CHTTP_Client::response(['error'], 500)]);
        $exception = null;

        try {
            $this->factory->retry(2, 0, function ($exception) {
                throw new RuntimeException('berhenti');
            }, true)->get('http://foo.com/get');
        } catch (RuntimeException $e) {
            $exception = $e;
        }

        $this->assertSame('berhenti', $exception->getMessage());
        $this->factory->assertSentCount(1);
    }

    public function testFailedRequest() {
        $exception = CHTTP_Client::failedRequest(['code' => 'not_found'], 404, ['X-RateLimit-Remaining' => 199]);

        $this->assertInstanceOf(CHTTP_Client_Exception_RequestException::class, $exception);
        $this->assertEquals(['code' => 'not_found'], $exception->response->json());
        $this->assertSame(404, $exception->response->status());
        $this->assertEquals(199, $exception->response->header('X-RateLimit-Remaining'));
    }

    public function testFakeConnectionException() {
        $this->factory->fake(CHTTP_Client::failedConnection('Fake'));
        $exception = null;

        try {
            $this->factory->post('https://example.com');
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(CHTTP_Client_Exception_ConnectionException::class, $exception);
        $this->assertSame('Fake', $exception->getMessage());
        $this->factory->assertSentCount(1);
        $this->factory->assertSent(function (CHTTP_Client_Request $request, $response) {
            return $request->url() === 'https://example.com' && $response === null;
        });
    }

    public function testFakeConnectionExceptionWithinArray() {
        $this->factory->fake(['*' => CHTTP_Client::failedConnection('Fake')]);

        $this->expectException(CHTTP_Client_Exception_ConnectionException::class);
        $this->expectExceptionMessage('Fake');
        $this->factory->post('https://example.com');
    }

    public function testFakeConnectionExceptionDefaultMessageNamesTheHost() {
        $this->factory->fake(CHTTP_Client::failedConnection());

        try {
            $this->factory->get('https://example.com/path');
            $this->fail('seharusnya melempar ConnectionException');
        } catch (CHTTP_Client_Exception_ConnectionException $e) {
            $this->assertStringContainsString('Could not resolve host: example.com', $e->getMessage());
        }
    }

    public function testMiddlewareRunsWhenFaked() {
        $this->factory->fake(function (CHTTP_Client_Request $request) {
            return CHTTP_Client::response('Fake');
        });
        $history = [];

        $response = $this->factory->withMiddleware(Middleware::history($history))->post('https://example.com', ['hyped-for' => 'cf']);

        $this->assertSame('Fake', $response->body());
        $this->assertCount(1, $history);
        $this->assertSame('Fake', c::tap($history[0]['response']->getBody())->rewind()->getContents());
        $this->assertSame(['hyped-for' => 'cf'], json_decode(c::tap($history[0]['request']->getBody())->rewind()->getContents(), true));
    }

    public function testMiddlewareRunsAndCanChangeRequestOnAssertSent() {
        $this->factory->fake(function (CHTTP_Client_Request $request) {
            return CHTTP_Client::response('Fake');
        });

        $this->factory->withMiddleware(Middleware::mapRequest(function (RequestInterface $request) {
            return $request->withHeader('X-Test-Header', 'Test');
        }))->post('https://cf.example', ['cf' => 'framework']);

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'https://cf.example' && $request->hasHeader('X-Test-Header', 'Test');
        });
    }

    public function testRequestAndResponseMiddleware() {
        $this->factory->fake(function (CHTTP_Client_Request $request) {
            return CHTTP_Client::response('Fake');
        });

        $response = $this->factory
            ->withRequestMiddleware(function (RequestInterface $request) {
                return $request->withHeader('X-Req', 'ya');
            })
            ->withResponseMiddleware(function (ResponseInterface $response) {
                return $response->withHeader('X-Res', 'ya');
            })
            ->get('https://example.com');

        $this->assertSame('ya', $response->header('X-Res'));
        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->hasHeader('X-Req', 'ya');
        });
    }

    public function testThrowOnThePendingRequest() {
        $this->factory->fake([
            'ok.com/*' => CHTTP_Client::response(['success'], 200),
            'fail.com/*' => CHTTP_Client::response(['error'], 403),
        ]);

        $this->assertSame(200, $this->factory->throw()->get('http://ok.com/get')->status());

        $this->expectException(CHTTP_Client_Exception_RequestException::class);
        $this->factory->throw()->get('http://fail.com/get');
    }

    public function testThrowIfOnThePendingRequest() {
        $this->factory->fake(['*' => CHTTP_Client::response(['error'], 403)]);

        $this->assertSame(403, $this->factory->throwIf(false)->get('http://foo.com/get')->status());
        $this->assertSame(403, $this->factory->throwIf(function ($response) {
            return $response->status() === 500;
        })->get('http://foo.com/get')->status());

        $this->expectException(CHTTP_Client_Exception_RequestException::class);
        $this->factory->throwIf(true)->get('http://foo.com/get');
    }

    public function testThrowUnlessOnThePendingRequest() {
        $this->factory->fake(['*' => CHTTP_Client::response(['error'], 403)]);

        $this->assertSame(403, $this->factory->throwUnless(true)->get('http://foo.com/get')->status());

        $this->expectException(CHTTP_Client_Exception_RequestException::class);
        $this->factory->throwUnless(false)->get('http://foo.com/get');
    }

    public function testThrowCallbackOnThePendingRequestRunsBeforeThrowing() {
        $this->factory->fake(['*' => CHTTP_Client::response(['error'], 403)]);
        $flag = false;
        $exception = null;

        try {
            $this->factory->throw(function ($exception) use (&$flag) {
                $flag = true;
            })->get('http://foo.com/get');
        } catch (CHTTP_Client_Exception_RequestException $e) {
            $exception = $e;
        }

        $this->assertTrue($flag);
        $this->assertInstanceOf(CHTTP_Client_Exception_RequestException::class, $exception);
    }

    public function testThrowOnTheResponse() {
        $this->factory->fake([
            'ok.com/*' => ['result' => ['foo' => 'bar']],
            'fail.com/*' => CHTTP_Client::response('', 400),
        ]);

        $response = $this->factory->get('http://ok.com/api')->throw();
        $this->assertSame('{"result":{"foo":"bar"}}', $response->body());

        $flag = false;
        $exception = null;

        try {
            $this->factory->get('http://fail.com/api')->throw(function () use (&$flag) {
                $flag = true;
            });
        } catch (CHTTP_Client_Exception_RequestException $e) {
            $exception = $e;
        }

        $this->assertTrue($flag);
        $this->assertInstanceOf(CHTTP_Client_Exception_RequestException::class, $exception);
    }

    public function testThrowIfAndThrowUnlessOnTheResponse() {
        $this->factory->fake(['*' => CHTTP_Client::response('', 400)]);

        $response = $this->factory->get('http://foo.com/api');
        $this->assertSame($response, $response->throwIf(false));
        $this->assertSame($response, $response->throwUnless(true));
        $this->assertSame($response, $response->throwIf(function ($response) {
            return $response->status() === 500;
        }));

        $this->expectException(CHTTP_Client_Exception_RequestException::class);
        $response->throwIf(function ($response) {
            return $response->status() === 400;
        });
    }

    public function testThrowIfStatus() {
        $this->factory->fake(['*' => CHTTP_Client::response('', 400)]);
        $response = $this->factory->get('http://foo.com/api');

        $this->assertSame($response, $response->throwIfStatus(500));
        $this->assertSame($response, $response->throwUnlessStatus(400));
        $this->assertSame($response, $response->throwIfServerError());

        $exception = null;

        try {
            $response->throwIfStatus(function ($status) {
                return $status === 400;
            });
        } catch (CHTTP_Client_Exception_RequestException $e) {
            $exception = $e;
        }
        $this->assertNotNull($exception);

        $this->expectException(CHTTP_Client_Exception_RequestException::class);
        $response->throwIfClientError();
    }

    public function testThrowUnlessStatusThrowsWhenTheStatusDiffers() {
        $this->factory->fake(['*' => CHTTP_Client::response('', 400)]);

        $this->expectException(CHTTP_Client_Exception_RequestException::class);
        $this->factory->get('http://foo.com/api')->throwUnlessStatus(200);
    }

    public function testItCanEnforceFaking() {
        $this->factory->preventStrayRequests();
        $this->factory->fake(['https://vapor.example.com' => CHTTP_Client::response('ok', 200)]);
        $this->factory->fake(['https://forge.example.com' => CHTTP_Client::response('ok', 200)]);

        $this->assertSame('ok', $this->factory->get('https://vapor.example.com')->body());
        $this->assertSame('ok', $this->factory->get('https://forge.example.com')->body());

        $this->expectException(CHTTP_Client_Exception_StrayRequestException::class);
        $this->expectExceptionMessage('Attempted request to [https://example.com] without a matching fake.');
        $this->factory->get('https://example.com');
    }

    public function testItCanEnforceFakingInThePool() {
        $this->factory->preventStrayRequests();
        $this->factory->fake(['https://vapor.example.com' => CHTTP_Client::response('ok', 200)]);

        $responses = $this->factory->pool(function (CHTTP_Client_Pool $pool) {
            return [$pool->get('https://vapor.example.com')];
        });
        $this->assertSame(200, $responses[0]->status());

        //pool() CF menunggu tiap promise berurutan, jadi request liar melempar (hulu mengembalikan
        //exception-nya sebagai anggota hasil)
        $this->expectException(CHTTP_Client_Exception_StrayRequestException::class);
        $this->factory->pool(function (CHTTP_Client_Pool $pool) {
            return [$pool->get('https://example.com')];
        });
    }

    public function testPreventingStrayRequests() {
        $factory = new CHTTP_Client();
        $this->assertFalse($factory->preventingStrayRequests());

        $factory->preventStrayRequests();
        $this->assertTrue($factory->preventingStrayRequests());

        $factory->allowStrayRequests();
        $this->assertFalse($factory->preventingStrayRequests());
    }

    public function testItCanAddAuthorizationHeaderIntoRequestUsingBeforeSendingCallback() {
        $this->factory->fake();

        $this->factory->beforeSending(function (CHTTP_Client_Request $request) {
            $requestLine = sprintf(
                '%s %s HTTP/%s',
                $request->toPsrRequest()->getMethod(),
                $request->toPsrRequest()->getUri()->withScheme('')->withHost(''),
                $request->toPsrRequest()->getProtocolVersion()
            );

            return $request->toPsrRequest()->withHeader('Authorization', 'Bearer ' . $requestLine);
        })->get('http://foo.com/json');

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'http://foo.com/json'
                && $request->hasHeader('Authorization', 'Bearer GET /json HTTP/1.1');
        });
    }

    public function testItCanSetAllowMaxRedirects() {
        $request = new CHTTP_Client_PendingRequest($this->factory);

        $request = $request->withOptions(['allow_redirects' => ['max' => 5]]);
        $this->assertSame(['max' => 5], $request->getOptions()['allow_redirects']);

        $request = $request->maxRedirects(10);
        $this->assertSame(['max' => 10], $request->getOptions()['allow_redirects']);

        $request = $request->withoutRedirecting();
        $this->assertFalse($request->getOptions()['allow_redirects']);
    }

    public function testTimeoutOptions() {
        $request = (new CHTTP_Client_PendingRequest($this->factory))->timeout(7)->connectTimeout(3);

        $this->assertSame(7, $request->getOptions()['timeout']);
        $this->assertSame(3, $request->getOptions()['connect_timeout']);
        $this->assertFalse((new CHTTP_Client_PendingRequest($this->factory))->withoutVerifying()->getOptions()['verify']);
    }

    public function testPreventDuplicatedContentType() {
        $client = $this->factory->asJson();
        $this->assertSame('application/json', carr::get($client->getOptions(), 'headers.Content-Type'));

        $client->asJson();
        $client->asJson();
        $this->assertSame('application/json', carr::get($client->getOptions(), 'headers.Content-Type'));

        $client->contentType('foo');
        $this->assertSame('foo', carr::get($client->getOptions(), 'headers.Content-Type'));
    }

    public function testBodyFormatFollowsTheHelpers() {
        $this->assertSame('json', $this->factory->asJson()->getBodyFormat());
        $this->assertSame('form_params', $this->factory->asForm()->getBodyFormat());
        $this->assertSame('multipart', $this->factory->asMultipart()->getBodyFormat());
        $this->assertSame('body', $this->factory->withBody('x', 'text/plain')->getBodyFormat());
    }

    public function testItCanSubstituteUrlParams() {
        $this->factory->fake();

        $this->factory->withUrlParameters([
            'endpoint' => 'https://example.com',
            'page' => 'docs',
            'version' => '9.x',
            'thing' => 'validation',
        ])->get('{+endpoint}/{page}/{version}/{thing}');

        $this->factory->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === 'https://example.com/docs/9.x/validation';
        });
    }

    public function testItCanAddGlobalRequestMiddleware() {
        $requests = [];
        $this->factory->fake(function ($r) use (&$requests) {
            $requests[] = $r;

            return CHTTP_Client::response('expected content');
        });

        $this->factory->globalRequestMiddleware(function ($request) {
            return $request->withHeader('User-Agent', 'CF/1.0');
        });
        $this->factory->post('http://forge.example.com');
        $this->factory->post('http://example.com');

        $this->assertSame(['CF/1.0'], $requests[0]->header('User-Agent'));
        $this->assertSame(['CF/1.0'], $requests[1]->header('User-Agent'));
    }

    public function testItCanAddGlobalResponseMiddleware() {
        $this->factory->fake(function ($r) {
            return CHTTP_Client::response('expected content');
        });

        $this->factory->globalResponseMiddleware(function ($response) {
            return $response->withHeader('X-Foo', 'Bar');
        });

        $this->assertSame('Bar', $this->factory->post('http://forge.example.com')->header('X-Foo'));
        $this->assertSame('Bar', $this->factory->post('http://example.com')->header('X-Foo'));
    }

    public function testItCanAddGlobalMiddlewareThatWrapsTheHandler() {
        $requests = [];
        $this->factory->fake(function ($r) use (&$requests) {
            $requests[] = $r;

            return CHTTP_Client::response('expected content');
        });

        $this->factory->globalMiddleware(Middleware::mapRequest(function ($request) {
            return $request->withHeader('User-Agent', 'CF/1.0')
                ->withAddedHeader('shared', 'global')
                ->withHeader('list', ['item-1', 'item-2'])
                ->withAddedHeader('list', ['item-3']);
        }))->globalMiddleware(Middleware::mapResponse(function ($response) use (&$requests) {
            return $response->withHeader('X-Count', (string) count($requests));
        }))->globalMiddleware(function ($handler) {
            return function ($request, $options) use ($handler) {
                return $handler($request, $options)->then(function (ResponseInterface $response) {
                    return $response->withHeader('X-Wrapped', 'yes');
                });
            };
        });

        $first = $this->factory->post('http://forge.example.com');
        $second = $this->factory->withHeader('shared', 'local')->post('http://vapor.example.com');

        $this->assertCount(2, $requests);
        $this->assertSame(['CF/1.0'], $requests[0]->header('User-Agent'));
        $this->assertSame(['item-1', 'item-2', 'item-3'], $requests[0]->header('list'));
        $this->assertSame(['global'], $requests[0]->header('shared'));
        $this->assertSame('1', $first->header('X-Count'));
        $this->assertSame('yes', $first->header('X-Wrapped'));

        $this->assertSame(['local', 'global'], $requests[1]->header('shared'));
        $this->assertSame('2', $second->header('X-Count'));
    }

    public function testItCanGetTheGlobalMiddleware() {
        $middleware = function () {
        };
        $this->factory->globalMiddleware($middleware);

        $this->assertSame([$middleware], $this->factory->getGlobalMiddleware());
    }

    public function testItCanHaveGlobalDefaultValues() {
        $timeout = null;
        $headers = null;
        $this->factory->fake(function ($request, $options) use (&$timeout, &$headers) {
            $timeout = $options['timeout'];
            $headers = $request->headers();

            return CHTTP_Client::response('');
        });

        $this->factory->get('https://example.com');
        $this->assertSame(30, $timeout);
        $this->assertNull(isset($headers['X-Foo']) ? $headers['X-Foo'] : null);

        $this->factory->globalOptions(['timeout' => 5, 'headers' => ['X-Foo' => 'true']]);
        $this->factory->get('https://example.com');
        $this->assertSame(5, $timeout);
        $this->assertSame(['true'], $headers['X-Foo']);
    }

    public function testItCanCreatePendingRequest() {
        $this->assertInstanceOf(CHTTP_Client_PendingRequest::class, $this->factory->createPendingRequest());
    }

    public function testResponseHeadersAreReadable() {
        $this->factory->fake(['*' => CHTTP_Client::response('x', 200, ['X-One' => '1', 'X-Two' => ['a', 'b']])]);

        $response = $this->factory->get('https://example.com');

        $this->assertSame('1', $response->header('X-One'));
        $this->assertSame('a, b', $response->header('X-Two'));
        $this->assertSame('', $response->header('X-Missing'));
        $this->assertSame(['a', 'b'], $response->headers()['X-Two']);
        $this->assertSame('OK', $response->reason());
        $this->assertInstanceOf(ResponseInterface::class, $response->toPsrResponse());
    }

    public function testJsonDecodingIsCached() {
        $this->factory->fake(['*' => ['a' => 1]]);
        $response = $this->factory->get('https://example.com');

        $this->assertSame(['a' => 1], $response->json());
        $this->assertSame(1, $response->json('a'));
        $this->assertNull($response->json('b'));
    }

    public function testInvalidJsonBodyGivesNull() {
        $this->factory->fake(['*' => 'bukan json']);
        $response = $this->factory->get('https://example.com');

        $this->assertNull($response->json());
        $this->assertSame('bukan json', $response->body());
    }
}
