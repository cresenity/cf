<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

/**
 * Tests for CHTTP_Request (system/libraries/CHTTP/Request.php).
 *
 * CHTTP_Request extends Symfony\Component\HttpFoundation\Request and is an
 * independent reimplementation of Laravel's Illuminate\Http\Request. This
 * suite only covers behavior added/overridden by CHTTP_Request itself (and
 * the CHTTP_Trait_InteractsWithInput / InteractsWithContentTypes /
 * InteractsWithFlashData traits it uses) - not Symfony's base Request
 * functionality.
 */
class HttpRequestTest extends TestCase {
    public function testMethod() {
        $request = CHTTP_Request::create('/', 'PUT');

        $this->assertSame('PUT', $request->method());
    }

    public function testRoot() {
        $request = CHTTP_Request::create('http://example.com/foo/bar', 'GET');

        $this->assertSame('http://example.com', $request->root());
    }

    public function testGetHostFallsBackToCFDomainWhenEmpty() {
        $request = CHTTP_Request::create('/', 'GET');
        $request->headers->set('HOST', '');
        $request->server->set('HTTP_HOST', '');
        $request->server->set('SERVER_NAME', '');

        // When there is no host information available at all, CHTTP_Request
        // falls back to CF::domain() instead of Symfony's default behavior.
        $this->assertSame(CF::domain(), $request->getHost());
    }

    public function testUrl() {
        $request = CHTTP_Request::create('http://example.com/foo/bar?baz=1', 'GET');

        $this->assertSame('http://example.com/foo/bar', $request->url());

        $request = CHTTP_Request::create('http://example.com/foo/bar/', 'GET');

        $this->assertSame('http://example.com/foo/bar', $request->url());
    }

    public function testFullUrl() {
        $request = CHTTP_Request::create('http://example.com/foo/bar?baz=1', 'GET');

        $this->assertSame('http://example.com/foo/bar?baz=1', $request->fullUrl());

        $request = CHTTP_Request::create('http://example.com/foo/bar', 'GET');

        $this->assertSame('http://example.com/foo/bar', $request->fullUrl());
    }

    public function testFullUrlWithQuery() {
        $request = CHTTP_Request::create('http://example.com/foo/bar?baz=1', 'GET');

        $result = $request->fullUrlWithQuery(['name' => 'Taylor']);

        $this->assertStringContainsString('baz=1', $result);
        $this->assertStringContainsString('name=Taylor', $result);
    }

    public function testPath() {
        $request = CHTTP_Request::create('http://example.com', 'GET');
        $this->assertSame('/', $request->path());

        $request = CHTTP_Request::create('http://example.com/foo/bar', 'GET');
        $this->assertSame('foo/bar', $request->path());
    }

    public function testDecodedPath() {
        $request = CHTTP_Request::create('http://example.com/foo%20bar', 'GET');

        $this->assertSame('foo bar', $request->decodedPath());
    }

    public function testSegment() {
        $request = CHTTP_Request::create('http://example.com/foo/bar', 'GET');

        $this->assertSame('foo', $request->segment(1));
        $this->assertSame('bar', $request->segment(2));
        $this->assertNull($request->segment(3));
        $this->assertSame('default', $request->segment(3, 'default'));
    }

    public function testSegments() {
        $request = CHTTP_Request::create('http://example.com/foo/bar', 'GET');

        $this->assertSame(['foo', 'bar'], $request->segments());
    }

    public function testIs() {
        $request = CHTTP_Request::create('http://example.com/foo/bar', 'GET');

        $this->assertTrue($request->is('foo/*'));
        $this->assertTrue($request->is('foo/bar'));
        $this->assertFalse($request->is('bar/*'));
        $this->assertTrue($request->is('*/bar'));
        $this->assertTrue($request->is('foo/*', 'baz/*'));
    }

    public function testFullUrlIs() {
        $request = CHTTP_Request::create('http://example.com/foo/bar?baz=1', 'GET');

        $this->assertTrue($request->fullUrlIs('http://example.com/foo/bar*'));
        $this->assertFalse($request->fullUrlIs('http://example.com/foo/bar'));
    }

    public function testAjax() {
        $request = CHTTP_Request::create('/', 'GET');
        $this->assertFalse($request->ajax());

        $request = CHTTP_Request::create('/', 'GET');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->assertTrue($request->ajax());
    }

    public function testPjax() {
        $request = CHTTP_Request::create('/', 'GET');
        $this->assertFalse($request->pjax());

        $request = CHTTP_Request::create('/', 'GET');
        $request->headers->set('X-PJAX', 'true');
        $this->assertTrue($request->pjax());
    }

    public function testPrefetch() {
        $request = CHTTP_Request::create('/', 'GET');
        $this->assertFalse($request->prefetch());

        $request = CHTTP_Request::create('/', 'GET');
        $request->headers->set('Purpose', 'prefetch');
        $this->assertTrue($request->prefetch());

        $request = CHTTP_Request::create('/', 'GET');
        $request->server->set('HTTP_X_MOZ', 'prefetch');
        $this->assertTrue($request->prefetch());
    }

    public function testSecure() {
        $request = CHTTP_Request::create('http://example.com', 'GET');
        $this->assertFalse($request->secure());

        $request = CHTTP_Request::create('https://example.com', 'GET');
        $this->assertTrue($request->secure());
    }

    public function testIp() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.1']);

        $this->assertSame('192.168.1.1', $request->ip());
    }

    public function testIps() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.1']);

        $this->assertSame(['192.168.1.1'], $request->ips());
    }

    public function testUserAgent() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Mozilla/5.0']);

        $this->assertSame('Mozilla/5.0', $request->userAgent());
    }

    public function testMerge() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Taylor']);

        $this->assertSame($request, $request->merge(['name' => 'Bob', 'age' => 30]));
        $this->assertSame('Bob', $request->input('name'));
        $this->assertSame(30, $request->input('age'));
    }

    public function testReplace() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Taylor']);

        $request->replace(['name' => 'Bob']);

        $this->assertSame('Bob', $request->input('name'));
        $this->assertSame(['name' => 'Bob'], $request->input());
    }

    public function testJson() {
        $payload = ['name' => 'Taylor', 'nested' => ['key' => 'value']];
        $request = CHTTP_Request::create('/', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertSame('Taylor', $request->json('name'));
        $this->assertSame('value', $request->json('nested.key'));
        $this->assertSame('default', $request->json('missing', 'default'));
        $this->assertInstanceOf(Symfony\Component\HttpFoundation\ParameterBag::class, $request->json());
        $this->assertSame($payload, $request->json()->all());
    }

    public function testInputSourceIsJsonBagWhenContentTypeIsJson() {
        $request = CHTTP_Request::create('/', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Taylor']));

        $this->assertSame('Taylor', $request->input('name'));
        $this->assertTrue($request->isJson());
    }

    public function testHeader() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['HTTP_X_CUSTOM' => 'foo']);

        $this->assertSame('foo', $request->header('X-Custom'));
        $this->assertNull($request->header('X-Missing'));
        $this->assertSame('default', $request->header('X-Missing', 'default'));
        $this->assertTrue($request->hasHeader('X-Custom'));
        $this->assertFalse($request->hasHeader('X-Missing'));
    }

    public function testBearerToken() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer some-token-value',
        ]);

        $this->assertSame('some-token-value', $request->bearerToken());
    }

    public function testBearerTokenWithComma() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer some-token,other-stuff',
        ]);

        $this->assertSame('some-token', $request->bearerToken());
    }

    public function testBearerTokenReturnsNullWhenAbsent() {
        $request = CHTTP_Request::create('/', 'GET');

        $this->assertNull($request->bearerToken());
    }

    public function testHas() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Taylor', 'nested' => ['key' => 'value']]);

        $this->assertTrue($request->has('name'));
        $this->assertTrue($request->has('nested.key'));
        $this->assertFalse($request->has('missing'));
        $this->assertTrue($request->has(['name', 'nested.key']));
        $this->assertFalse($request->has(['name', 'missing']));
        $this->assertTrue($request->exists('name'));
    }

    public function testHasAny() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Taylor']);

        $this->assertTrue($request->hasAny(['name', 'missing']));
        $this->assertFalse($request->hasAny(['missing', 'alsomissing']));
    }

    public function testWhenHas() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Taylor']);

        $captured = null;
        $result = $request->whenHas('name', function ($value) use (&$captured) {
            $captured = $value;

            return 'called';
        });
        $this->assertSame('Taylor', $captured);
        $this->assertSame('called', $result);

        $defaultCalled = false;
        $request->whenHas('missing', function () {
        }, function () use (&$defaultCalled) {
            $defaultCalled = true;
        });
        $this->assertTrue($defaultCalled);
    }

    public function testFilled() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Taylor', 'empty' => '']);

        $this->assertTrue($request->filled('name'));
        $this->assertFalse($request->filled('empty'));
        $this->assertFalse($request->filled('missing'));
    }

    public function testMissing() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Taylor']);

        $this->assertFalse($request->missing('name'));
        $this->assertTrue($request->missing('absent'));
    }

    public function testWhenFilled() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Taylor', 'empty' => '']);

        $captured = null;
        $request->whenFilled('name', function ($value) use (&$captured) {
            $captured = $value;
        });
        $this->assertSame('Taylor', $captured);

        $defaultCalled = false;
        $request->whenFilled('empty', function () {
        }, function () use (&$defaultCalled) {
            $defaultCalled = true;
        });
        $this->assertTrue($defaultCalled);
    }

    public function testAll() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Taylor', 'age' => 30]);

        $this->assertSame(['name' => 'Taylor', 'age' => 30], $request->all());
    }

    public function testAllWithKeys() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Taylor', 'age' => 30]);

        $this->assertSame(['name' => 'Taylor'], $request->all('name'));
        $this->assertSame(['name' => 'Taylor', 'age' => 30], $request->all(['name', 'age']));
    }

    public function testInput() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Taylor', 'nested' => ['key' => 'value']]);

        $this->assertSame('Taylor', $request->input('name'));
        $this->assertSame('value', $request->input('nested.key'));
        $this->assertSame('fallback', $request->input('missing', 'fallback'));
        $this->assertSame(['name' => 'Taylor', 'nested' => ['key' => 'value']], $request->input());
    }

    public function testInputMergesQueryAndPostFavoringPost() {
        $request = CHTTP_Request::create('/?name=Query', 'POST', ['name' => 'Post']);

        $this->assertSame('Post', $request->input('name'));
    }

    public function testOnly() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Taylor', 'age' => 30, 'city' => 'NYC']);

        $this->assertSame(['name' => 'Taylor', 'age' => 30], $request->only(['name', 'age']));
        $this->assertSame(['name' => 'Taylor'], $request->only('name'));
        $this->assertSame([], $request->only('missing'));
    }

    public function testExcept() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Taylor', 'age' => 30, 'city' => 'NYC']);

        $this->assertSame(['age' => 30, 'city' => 'NYC'], $request->except(['name']));
        $this->assertSame(['name' => 'Taylor', 'age' => 30, 'city' => 'NYC'], $request->except(['missing']));
    }

    public function testQuery() {
        $request = CHTTP_Request::create('/?name=Taylor', 'GET');

        $this->assertSame('Taylor', $request->query('name'));
        $this->assertSame(['name' => 'Taylor'], $request->query());
        $this->assertNull($request->query('missing'));
        $this->assertSame('default', $request->query('missing', 'default'));
    }

    public function testPost() {
        $request = CHTTP_Request::create('/', 'POST', ['name' => 'Taylor']);

        $this->assertSame('Taylor', $request->post('name'));
        $this->assertSame(['name' => 'Taylor'], $request->post());
    }

    public function testBoolean() {
        $request = CHTTP_Request::create('/', 'GET', [
            'on' => 'on', 'yes' => 'yes', 'true' => 'true', 'one' => '1',
            'off' => 'off', 'zero' => '0', 'random' => 'random',
        ]);

        $this->assertTrue($request->boolean('on'));
        $this->assertTrue($request->boolean('yes'));
        $this->assertTrue($request->boolean('true'));
        $this->assertTrue($request->boolean('one'));
        $this->assertFalse($request->boolean('off'));
        $this->assertFalse($request->boolean('zero'));
        $this->assertFalse($request->boolean('random'));
        $this->assertFalse($request->boolean('missing'));
    }

    public function testInteger() {
        $request = CHTTP_Request::create('/', 'GET', ['age' => '30']);

        $this->assertSame(30, $request->integer('age'));
        $this->assertSame(0, $request->integer('missing'));
        $this->assertSame(99, $request->integer('missing', 99));
    }

    public function testFloatMethod() {
        $request = CHTTP_Request::create('/', 'GET', ['price' => '9.99']);

        $this->assertSame(9.99, $request->float('price'));
        $this->assertSame(0.0, $request->float('missing'));
    }

    public function testStringMethod() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Taylor']);

        $this->assertInstanceOf(CBase_String::class, $request->string('name'));
        $this->assertSame('Taylor', (string) $request->string('name'));
        $this->assertSame((string) $request->str('name'), (string) $request->string('name'));
    }

    public function testCollect() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Taylor', 'age' => 30]);

        $collection = $request->collect();
        $this->assertInstanceOf(CCollection::class, $collection);
        $this->assertSame(['name' => 'Taylor', 'age' => 30], $collection->all());

        $subset = $request->collect(['name']);
        $this->assertSame(['name' => 'Taylor'], $subset->all());
    }

    public function testKeys() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Taylor', 'age' => 30]);

        $this->assertSame(['name', 'age'], $request->keys());
    }

    public function testCookieMethod() {
        $request = CHTTP_Request::create('/', 'GET', [], ['flavor' => 'chocolate']);

        $this->assertSame('chocolate', $request->cookie('flavor'));
        $this->assertNull($request->cookie('missing'));
        $this->assertTrue($request->hasCookie('flavor'));
        $this->assertFalse($request->hasCookie('missing'));
    }

    public function testServerMethod() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['FOO' => 'bar']);

        $this->assertSame('bar', $request->server('FOO'));
        $this->assertNull($request->server('MISSING'));
    }

    public function testHasFileAndFile() {
        $tmpFile = tempnam(sys_get_temp_dir(), 'chttp_test_');
        file_put_contents($tmpFile, 'hello world');

        $uploaded = new SymfonyUploadedFile($tmpFile, 'hello.txt', 'text/plain', null, true);

        $request = CHTTP_Request::create('/', 'POST', [], [], ['document' => $uploaded]);

        $this->assertTrue($request->hasFile('document'));
        $this->assertFalse($request->hasFile('missing'));

        $file = $request->file('document');
        $this->assertInstanceOf(CHTTP_UploadedFile::class, $file);
        $this->assertSame('hello.txt', $file->getClientOriginalName());

        $this->assertArrayHasKey('document', $request->allFiles());

        @unlink($tmpFile);
    }

    public function testMergeMutatesInputSourceForPostRequest() {
        $request = CHTTP_Request::create('/', 'POST', ['a' => 1]);
        $request->merge(['b' => 2]);

        $this->assertSame(['a' => 1, 'b' => 2], $request->post());
    }

    public function testWantsJson() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->assertTrue($request->wantsJson());

        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html']);
        $this->assertFalse($request->wantsJson());
    }

    public function testExpectsJson() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->assertTrue($request->expectsJson());

        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $this->assertTrue($request->expectsJson());

        $request = CHTTP_Request::create('/', 'GET', [], [], [], [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTP_X_PJAX' => 'true',
        ]);
        $this->assertFalse($request->expectsJson());

        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html']);
        $this->assertFalse($request->expectsJson());
    }

    public function testAcceptsJsonAndHtml() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->assertTrue($request->acceptsJson());
        $this->assertFalse($request->acceptsHtml());

        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html']);
        $this->assertTrue($request->acceptsHtml());
        $this->assertFalse($request->acceptsJson());

        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => '*/*']);
        $this->assertTrue($request->acceptsJson());
        $this->assertTrue($request->acceptsHtml());
    }

    public function testAccepts() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json, text/html']);

        $this->assertTrue($request->accepts('application/json'));
        $this->assertTrue($request->accepts(['text/plain', 'text/html']));
        $this->assertFalse($request->accepts('application/xml'));
    }

    public function testPrefers() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json, text/html']);

        $this->assertSame('application/json', $request->prefers(['text/html', 'application/json']));
        $this->assertNull($request->prefers('application/xml'));
    }

    public function testFormat() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->assertSame('json', $request->format());

        // With no Accept header at all, Symfony's default acceptable content
        // types include text/html, so format() resolves to 'html' rather
        // than falling back to the given default.
        $request = CHTTP_Request::create('/', 'GET');
        $this->assertSame('html', $request->format());

        // A default is only used when nothing in the (explicit) Accept
        // header maps to a known format.
        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/unknown-xyz']);
        $this->assertSame('custom', $request->format('custom'));
    }

    public function testMatchesType() {
        // matchesType($actual, $type) checks whether $type is a structured
        // syntax suffix (e.g. "+json") variant of $actual - so the plain
        // type is passed first and the suffixed one second.
        $this->assertTrue(CHTTP_Request::matchesType('application/json', 'application/json'));
        $this->assertTrue(CHTTP_Request::matchesType('application/json', 'application/ld+json'));
        $this->assertFalse(CHTTP_Request::matchesType('application/xml', 'application/json'));
    }

    public function testToArray() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Taylor']);

        $this->assertSame($request->all(), $request->toArray());
    }

    public function testArrayAccess() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Taylor']);

        $this->assertTrue(isset($request['name']));
        $this->assertFalse(isset($request['missing']));
        $this->assertSame('Taylor', $request['name']);

        $request['city'] = 'NYC';
        $this->assertSame('NYC', $request['city']);
        $this->assertTrue(isset($request['city']));

        unset($request['city']);
        $this->assertFalse(isset($request['city']));
    }

    public function testMagicIssetAndGet() {
        $request = CHTTP_Request::create('/', 'GET', ['name' => 'Taylor']);

        $this->assertTrue(isset($request->name));
        $this->assertSame('Taylor', $request->name);
        $this->assertFalse(isset($request->missing));
        $this->assertNull($request->missing);
    }

    public function testRouteReturnsNullWithoutResolver() {
        $request = CHTTP_Request::create('/', 'GET');

        $this->assertNull($request->route());
    }

    public function testRouteResolver() {
        $request = CHTTP_Request::create('/', 'GET');
        $route = new stdClass();
        $request->setRouteResolver(function () use ($route) {
            return $route;
        });

        $this->assertSame($route, $request->route());
    }

    public function testRouteWithParam() {
        $request = CHTTP_Request::create('/', 'GET');

        $fakeRoute = new class() {
            public function parameter($name, $default = null) {
                return $name === 'id' ? 42 : $default;
            }
        };

        $request->setRouteResolver(function () use ($fakeRoute) {
            return $fakeRoute;
        });

        $this->assertSame(42, $request->route('id'));
        $this->assertSame('fallback', $request->route('missing', 'fallback'));
    }

    public function testUserResolver() {
        $request = CHTTP_Request::create('/', 'GET');
        $user = new stdClass();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $this->assertSame($user, $request->user());
    }

    public function testInstance() {
        $request = CHTTP_Request::create('/', 'GET');

        $this->assertSame($request, $request->instance());
    }

    public function testCapture() {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/captured';
        $_SERVER['HTTP_HOST'] = 'example.com';

        $request = CHTTP_Request::capture();

        $this->assertInstanceOf(CHTTP_Request::class, $request);
        // path() trims slashes, so the leading "/" is stripped.
        $this->assertSame('captured', $request->path());
    }

    public function testCreateFromBaseReturnsCHTTPRequest() {
        $symfonyRequest = Symfony\Component\HttpFoundation\Request::create('/foo?bar=baz', 'GET');

        $request = CHTTP_Request::createFromBase($symfonyRequest);

        $this->assertInstanceOf(CHTTP_Request::class, $request);
        $this->assertSame('foo', $request->path());
        $this->assertSame('baz', $request->query('bar'));
    }

    public function testCreateFrom() {
        $original = CHTTP_Request::create('/foo', 'GET', ['name' => 'Taylor']);

        $new = CHTTP_Request::createFrom($original);

        $this->assertInstanceOf(CHTTP_Request::class, $new);
        $this->assertNotSame($original, $new);
        $this->assertSame('Taylor', $new->input('name'));
    }

    public function testBrowser() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);

        $browser = $request->browser();
        $this->assertInstanceOf(CBrowser::class, $browser);
        // Calling twice must return the same cached instance.
        $this->assertSame($browser, $request->browser());
    }

    public function testIsCresRequest() {
        $request = CHTTP_Request::create('/', 'GET');
        $this->assertFalse($request->isCresRequest());

        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['HTTP_X_CRES_VERSION' => '1.0']);
        $this->assertTrue($request->isCresRequest());
    }

    public function testReferrerReturnsDefaultWhenAbsent() {
        $request = CHTTP_Request::create('/', 'GET');

        $this->assertFalse($request->referrer());
        $this->assertSame('none', $request->referrer('none'));
    }

    public function testReferrerReturnsHeaderValue() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], [
            'HTTP_REFERER' => 'http://external.example/from-here',
        ]);

        $this->assertSame('http://external.example/from-here', $request->referrer());
    }

    public function testOldReturnsDefaultWhenNothingWasFlashed() {
        CBase::session()->forget('_old_input');
        $request = CHTTP_Request::create('/', 'GET');

        $this->assertNull($request->old('name'));
        $this->assertSame('fallback', $request->old('name', 'fallback'));
    }

    public function testOldReadsTheFlashedInputOfTheCurrentSession() {
        $request = CHTTP_Request::create('/', 'GET');
        CBase::session()->put('_old_input', ['name' => 'Budi', 'kota' => 'Bandung']);

        try {
            $this->assertTrue($request->hasSession(), 'sesi CF ada walau tidak dipasang ke request Symfony');
            $this->assertSame('Budi', $request->old('name'));
            $this->assertSame('Bandung', $request->old('kota', 'x'));
            $this->assertSame('x', $request->old('tidak-ada', 'x'));
            $this->assertSame('Budi', c::old('name'), 'helper c::old() lewat request aktif');
        } finally {
            CBase::session()->forget('_old_input');
        }
    }

    public function testFingerprintThrowsWithoutRoute() {
        $request = CHTTP_Request::create('/', 'GET');

        $this->expectException(RuntimeException::class);
        $request->fingerprint();
    }

    public function testFingerprint() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.1']);

        $fakeRoute = new class() {
            public function methods() {
                return ['GET', 'HEAD'];
            }

            public function getDomain() {
                return 'example.com';
            }

            public function uri() {
                return 'foo/bar';
            }
        };

        $request->setRouteResolver(function () use ($fakeRoute) {
            return $fakeRoute;
        });

        $expected = sha1(implode('|', ['GET', 'HEAD', 'example.com', 'foo/bar', '10.0.0.1']));
        $this->assertSame($expected, $request->fingerprint());
    }

    public function testGetSessionReturnsDecoratorWhenSessionAvailable() {
        // Unlike Symfony's own hasSession()/getSession() (which rely on a
        // session explicitly attached via setSession()), CHTTP_Request's
        // getSession() always defers to session() -> CBase::session(),
        // which lazily boots a session from the app's configured driver
        // (SESSION_DRIVER=array in the test environment). So it resolves a
        // decorator rather than throwing SessionNotFoundException.
        $request = CHTTP_Request::create('/', 'GET');

        $session = $request->getSession();

        $this->assertInstanceOf(CSession_SymfonySessionDecorator::class, $session);
    }

    public function testSessionReturnsStore() {
        $request = CHTTP_Request::create('/', 'GET');

        $this->assertInstanceOf(CSession_Store::class, $request->session());
    }

    public function testSetJsonAndGetJson() {
        $request = CHTTP_Request::create('/', 'GET');

        $bag = new Symfony\Component\HttpFoundation\ParameterBag(['name' => 'Taylor']);
        $this->assertSame($request, $request->setJson($bag));
        $this->assertSame($bag, $request->json());
    }
}
