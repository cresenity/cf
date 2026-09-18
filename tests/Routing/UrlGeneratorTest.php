<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * CRouting_UrlGenerator - padanan suite hulu. Koleksi rute dimiliki test (lewat subclass yang
 * meng-override routes()) supaya tidak menyentuh router global proses PHPUnit.
 */
class UrlGeneratorTest extends TestCase {
    /**
     * @param string $requestUrl
     *
     * @return UrlGeneratorTestGenerator
     */
    private function generator($requestUrl = 'http://www.foo.com/') {
        $url = new UrlGeneratorTestGenerator();
        $url->setRequest(CHTTP_Request::create($requestUrl));

        return $url;
    }

    /**
     * @param array  $methods
     * @param string $uri
     * @param array  $action
     *
     * @return CRouting_Route
     */
    private function route($methods, $uri, array $action = []) {
        return new CRouting_Route($methods, $uri, $action);
    }

    public function testBasicGeneration() {
        $url = $this->generator();

        $this->assertSame('http://www.foo.com/foo/bar', $url->to('foo/bar'));
        $this->assertSame('https://www.foo.com/foo/bar', $url->to('foo/bar', [], true));
        $this->assertSame('https://www.foo.com/foo/bar/baz/boom', $url->to('foo/bar', ['baz', 'boom'], true));
        $this->assertSame('https://www.foo.com/foo/bar/baz?foo=bar', $url->to('foo/bar?foo=bar', ['baz'], true));
        $this->assertSame('https://www.foo.com/foo/bar', $url->secure('foo/bar'));

        $url = $this->generator('https://www.foo.com/');
        $this->assertSame('https://www.foo.com/foo/bar', $url->to('foo/bar'));
    }

    public function testToLeavesAValidUrlAlone() {
        $url = $this->generator();

        $this->assertSame('http://other.com/x', $url->to('http://other.com/x'));
        $this->assertSame('//cdn.com/x.js', $url->to('//cdn.com/x.js'));
        $this->assertSame('mailto:a@b.c', $url->to('mailto:a@b.c'));
        $this->assertSame('#top', $url->to('#top'));
    }

    public function testAssetFromStripsIndexPhp() {
        $url = $this->generator('http://www.foo.com/index.php/');

        $this->assertSame('http://www.foo.com/foo/bar', $url->assetFrom('http://www.foo.com/index.php', 'foo/bar'));
        $this->assertSame('https://www.foo.com/foo/bar', $url->assetFrom('http://www.foo.com/index.php', 'foo/bar', true));
        $this->assertSame('http://cdn.foo.com/foo/bar', $url->mediaFrom('http://cdn.foo.com', '/foo/bar/'));
    }

    public function testBasicGenerationWithHostFormatting() {
        $url = $this->generator();
        $url->routes->add($this->route(['GET'], '/named-route', ['as' => 'plain']));
        $url->formatHostUsing(function ($host) {
            return str_replace('foo.com', 'foo.org', $host);
        });

        $this->assertSame('http://www.foo.org/foo/bar', $url->to('foo/bar'));
        $this->assertSame('/named-route', $url->route('plain', [], false));
    }

    public function testBasicGenerationWithRequestBaseUrlWithSubfolder() {
        $request = CHTTP_Request::create('http://www.foo.com/subfolder/foo/bar/subfolder/');
        $request->server->set('SCRIPT_FILENAME', '/var/www/project/public/subfolder/index.php');
        $request->server->set('PHP_SELF', '/subfolder/index.php');
        $url = new UrlGeneratorTestGenerator();
        $url->setRequest($request);
        $url->routes->add($this->route(['GET'], 'foo/bar/subfolder', ['as' => 'foobar']));

        $this->assertSame('/subfolder', $request->getBaseUrl());
        $this->assertSame('/foo/bar/subfolder', $url->route('foobar', [], false));
    }

    public function testBasicGenerationWithPathFormatting() {
        $url = $this->generator();
        $url->routes->add($this->route(['GET'], '/named-route', ['as' => 'plain']));
        $url->formatPathUsing(function ($path) {
            return '/something' . $path;
        });

        $this->assertSame('http://www.foo.com/something/foo/bar', $url->to('foo/bar'));
        $this->assertSame('/something/named-route', $url->route('plain', [], false));
    }

    public function testUrlFormattersReceiveTheTargetRoute() {
        $url = $this->generator('http://abc.com/');
        $url->routes->add($this->route(['GET'], '/bar', ['as' => 'plain', 'root' => 'bar.com', 'path' => 'foo']));
        $url->formatHostUsing(function ($root, $route) {
            return $route ? 'http://' . $route->getAction('root') : $root;
        });
        $url->formatPathUsing(function ($path, $route) {
            return $route ? '/' . $route->getAction('path') : $path;
        });

        $this->assertSame('http://abc.com/foo/bar', $url->to('foo/bar'));
        $this->assertSame('http://bar.com/foo', $url->route('plain'));
    }

    public function testBasicRouteGeneration() {
        $url = $this->generator();
        $routes = $url->routes;
        $routes->add($this->route(['GET'], '/', ['as' => 'plain']));
        $routes->add($this->route(['GET'], 'foo/bar', ['as' => 'foo']));
        $routes->add($this->route(['GET'], 'foo/bar/{baz}/breeze/{boom}', ['as' => 'bar']));
        $routes->add($this->route(['GET'], 'foo/bar/{baz}', ['as' => 'foobar']));
        $routes->add($this->route(['GET'], 'foo/bar/{baz?}', ['as' => 'optional']));
        $routes->add($this->route(['GET'], 'foo/baz', ['as' => 'baz', 'https']));
        $routes->add($this->route(['GET'], 'foo/bam', ['controller' => 'foo@bar']));
        $routes->add($this->route(['GET'], 'foo/bar/åαф/{baz}', ['as' => 'foobarbaz']));
        $routes->add($this->route(['GET'], 'foo/bar#derp', ['as' => 'fragment']));
        $routes->add($this->route(['GET'], 'foo/invoke', ['controller' => 'UrlGeneratorTestInvokable']));
        $url->defaults(['locale' => 'en']);
        $routes->add($this->route(['GET'], 'foo', ['as' => 'defaults', 'domain' => '{locale}.example.com', function () {
        }]));

        $this->assertSame('/', $url->route('plain', [], false));
        $this->assertSame('/?foo=bar', $url->route('plain', ['foo' => 'bar'], false));
        $this->assertSame('http://www.foo.com/foo/bar', $url->route('foo'));
        $this->assertSame('/foo/bar', $url->route('foo', [], false));
        $this->assertSame('/foo/bar?foo=bar', $url->route('foo', ['foo' => 'bar'], false));
        $this->assertSame('http://www.foo.com/foo/bar/taylor/breeze/otwell?fly=wall', $url->route('bar', ['taylor', 'otwell', 'fly' => 'wall']));
        $this->assertSame('http://www.foo.com/foo/bar/otwell/breeze/taylor?fly=wall', $url->route('bar', ['boom' => 'taylor', 'baz' => 'otwell', 'fly' => 'wall']));
        $this->assertSame('http://www.foo.com/foo/bar/0', $url->route('foobar', 0));
        $this->assertSame('http://www.foo.com/foo/bar/2', $url->route('foobar', 2));
        $this->assertSame('http://www.foo.com/foo/bar/taylor', $url->route('foobar', 'taylor'));
        $this->assertSame('/foo/bar/taylor/breeze/otwell?fly=wall', $url->route('bar', ['taylor', 'otwell', 'fly' => 'wall'], false));
        $this->assertSame('https://www.foo.com/foo/baz', $url->route('baz'));
        $this->assertSame('http://www.foo.com/foo/bam', $url->action('foo@bar'));
        $this->assertSame('http://www.foo.com/foo/bam', $url->action(['foo', 'bar']));
        $this->assertSame('http://www.foo.com/foo/invoke', $url->action('UrlGeneratorTestInvokable'));
        $this->assertSame('http://www.foo.com/foo/bar/taylor/breeze/otwell?wall&woz', $url->route('bar', ['wall', 'woz', 'boom' => 'otwell', 'baz' => 'taylor']));
        $this->assertSame('http://www.foo.com/foo/bar/taylor/breeze/otwell?wall&woz', $url->route('bar', ['taylor', 'otwell', 'wall', 'woz']));
        $this->assertSame('http://www.foo.com/foo/bar', $url->route('optional'));
        $this->assertSame('http://www.foo.com/foo/bar', $url->route('optional', ['baz' => null]));
        $this->assertSame('http://www.foo.com/foo/bar', $url->route('optional', ['baz' => '']));
        $this->assertSame('http://www.foo.com/foo/bar/0', $url->route('optional', ['baz' => 0]));
        $this->assertSame('http://www.foo.com/foo/bar/taylor', $url->route('optional', 'taylor'));
        $this->assertSame('http://www.foo.com/foo/bar/taylor', $url->route('optional', ['taylor']));
        $this->assertSame('http://www.foo.com/foo/bar/taylor?breeze', $url->route('optional', ['taylor', 'breeze']));
        $this->assertSame('http://www.foo.com/foo/bar/taylor?wall=woz', $url->route('optional', ['wall' => 'woz', 'taylor']));
        $this->assertSame('http://www.foo.com/foo/bar/taylor?wall=woz&breeze', $url->route('optional', ['wall' => 'woz', 'breeze', 'baz' => 'taylor']));
        $this->assertSame('http://www.foo.com/foo/bar?wall=woz', $url->route('optional', ['wall' => 'woz']));
        $this->assertSame('http://www.foo.com/foo/bar/%C3%A5%CE%B1%D1%84/%C3%A5%CE%B1%D1%84', $url->route('foobarbaz', ['baz' => 'åαф']));
        $this->assertSame('/foo/bar#derp', $url->route('fragment', [], false));
        $this->assertSame('/foo/bar?foo=bar#derp', $url->route('fragment', ['foo' => 'bar'], false));
        $this->assertSame('/foo/bar?baz=%C3%A5%CE%B1%D1%84#derp', $url->route('fragment', ['baz' => 'åαф'], false));
        $this->assertSame('http://en.example.com/foo', $url->route('defaults'));
    }

    public function testFluentRouteNameDefinitions() {
        $url = $this->generator();
        $route = $this->route(['GET'], 'foo/bar');
        $route->name('foo');
        $url->routes->add($route);
        $url->routes->refreshNameLookups();

        $this->assertSame('http://www.foo.com/foo/bar', $url->route('foo'));
    }

    public function testControllerRoutesWithADefaultNamespace() {
        $url = $this->generator();
        $url->setRootControllerNamespace('namespace');
        $url->routes->add($this->route(['GET'], 'foo/bar', ['controller' => 'namespace\foo@bar']));
        $url->routes->add($this->route(['GET'], 'something/else', ['controller' => 'something\foo@bar']));
        $url->routes->add($this->route(['GET'], 'foo/invoke', ['controller' => 'namespace\UrlGeneratorTestInvokable']));

        $this->assertSame('http://www.foo.com/foo/bar', $url->action('foo@bar'));
        $this->assertSame('http://www.foo.com/something/else', $url->action('\something\foo@bar'));
        $this->assertSame('http://www.foo.com/foo/invoke', $url->action('UrlGeneratorTestInvokable'));
    }

    public function testControllerRoutesOutsideOfDefaultNamespace() {
        $url = $this->generator();
        $url->setRootControllerNamespace('namespace');
        $url->routes->add($this->route(['GET'], 'root/namespace', ['controller' => '\root\namespace@foo']));
        $url->routes->add($this->route(['GET'], 'invokable/namespace', ['controller' => '\root\namespace\UrlGeneratorTestInvokable']));

        $this->assertSame('http://www.foo.com/root/namespace', $url->action('\root\namespace@foo'));
        $this->assertSame('http://www.foo.com/invokable/namespace', $url->action('\root\namespace\UrlGeneratorTestInvokable'));
    }

    public function testActionNotDefinedThrows() {
        $url = $this->generator();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Action tidak@ada not defined.');
        $url->action('tidak@ada');
    }

    public function testRoutableInterfaceRouting() {
        $url = $this->generator();
        $url->routes->add($this->route(['GET'], 'foo/{bar}', ['as' => 'routable']));
        $model = new UrlGeneratorTestRoutable();
        $model->key = 'routable';

        $this->assertSame('/foo/routable', $url->route('routable', [$model], false));
        $this->assertSame('/foo/routable', $url->route('routable', $model, false));
    }

    public function testRoutableInterfaceRoutingWithCustomBindingField() {
        $url = $this->generator();
        $url->routes->add($this->route(['GET'], 'foo/{bar:slug}', ['as' => 'routable']));
        $model = new UrlGeneratorTestRoutable();
        $model->key = 'routable';

        $this->assertSame('/foo/test-slug', $url->route('routable', ['bar' => $model], false));
        $this->assertSame('/foo/test-slug', $url->route('routable', [$model], false));
    }

    public function testRoutableInterfaceRoutingAsQueryString() {
        $url = $this->generator();
        $url->routes->add($this->route(['GET'], 'foo', ['as' => 'query-string']));
        $model = new UrlGeneratorTestRoutable();
        $model->key = 'routable';

        $this->assertSame('/foo?routable', $url->route('query-string', $model, false));
        $this->assertSame('/foo?routable', $url->route('query-string', [$model], false));
        $this->assertSame('/foo?foo=routable', $url->route('query-string', ['foo' => $model], false));
    }

    public function testRoutableInterfaceRoutingWithSeparateBindingFieldOnlyForSecondParameter() {
        $url = $this->generator();
        $url->routes->add($this->route(['GET'], 'foo/{bar}/{baz:slug}', ['as' => 'routable']));
        $model1 = new UrlGeneratorTestRoutable();
        $model1->key = 'routable-1';
        $model2 = new UrlGeneratorTestRoutable();
        $model2->key = 'routable-2';

        $this->assertSame('/foo/routable-1/test-slug', $url->route('routable', ['bar' => $model1, 'baz' => $model2], false));
        $this->assertSame('/foo/routable-1/test-slug', $url->route('routable', [$model1, $model2], false));
    }

    public function testRoutesMaintainRequestScheme() {
        $url = $this->generator('https://www.foo.com/');
        $url->routes->add($this->route(['GET'], 'foo/bar', ['as' => 'foo']));

        $this->assertSame('https://www.foo.com/foo/bar', $url->route('foo'));
    }

    public function testHttpOnlyRoutes() {
        $url = $this->generator('https://www.foo.com/');
        $url->routes->add($this->route(['GET'], 'foo/bar', ['as' => 'foo', 'http']));

        $this->assertSame('http://www.foo.com/foo/bar', $url->route('foo'));
    }

    public function testRoutesWithDomains() {
        $url = $this->generator();
        $url->routes->add($this->route(['GET'], 'foo/bar', ['as' => 'foo', 'domain' => 'sub.foo.com']));
        $url->routes->add($this->route(['GET'], 'foo/bar/{baz}', ['as' => 'bar', 'domain' => 'sub.{foo}.com']));

        $this->assertSame('http://sub.foo.com/foo/bar', $url->route('foo'));
        $this->assertSame('http://sub.taylor.com/foo/bar/otwell', $url->route('bar', ['taylor', 'otwell']));
        $this->assertSame('/foo/bar/otwell', $url->route('bar', ['taylor', 'otwell'], false));
    }

    public function testRoutesWithDomainsAndPorts() {
        $url = $this->generator('http://www.foo.com:8080/');
        $url->routes->add($this->route(['GET'], 'foo/bar', ['as' => 'foo', 'domain' => 'sub.foo.com']));
        $url->routes->add($this->route(['GET'], 'foo/bar/{baz}', ['as' => 'bar', 'domain' => 'sub.{foo}.com']));

        $this->assertSame('http://sub.foo.com:8080/foo/bar', $url->route('foo'));
        $this->assertSame('http://sub.taylor.com:8080/foo/bar/otwell', $url->route('bar', ['taylor', 'otwell']));
    }

    public function testRoutesWithDomainsStripProtocols() {
        $url = $this->generator();
        $url->routes->add($this->route(['GET'], 'foo/bar', ['as' => 'foo', 'domain' => 'http://sub.foo.com']));
        $this->assertSame('http://sub.foo.com/foo/bar', $url->route('foo'));

        $url = $this->generator('https://www.foo.com/');
        $url->routes->add($this->route(['GET'], 'foo/bar', ['as' => 'foo', 'domain' => 'https://sub.foo.com']));
        $this->assertSame('https://sub.foo.com/foo/bar', $url->route('foo'));
    }

    public function testHttpsRoutesWithDomains() {
        $url = $this->generator('https://foo.com/');
        $url->routes->add($this->route(['GET'], 'foo/bar', ['as' => 'baz', 'domain' => 'sub.foo.com']));

        $this->assertSame('https://sub.foo.com/foo/bar', $url->route('baz'));
    }

    /**
     * @return array
     */
    public function missingParameterProvider() {
        return [
            [['test' => 123]],
            [['one' => null, 'test' => 123]],
            [['one' => '', 'test' => 123]],
        ];
    }

    /**
     * @dataProvider missingParameterProvider
     *
     * @param array $parameters
     */
    public function testUrlGenerationRequiresTheRequiredParameters($parameters) {
        $url = $this->generator('http://www.foo.com:8080/');
        $url->routes->add($this->route(['GET'], 'foo/{one}/{two?}/{three?}', ['as' => 'foo', function () {
        }]));

        $this->expectException(CRouting_Exception_UrlGenerationException::class);
        $this->expectExceptionMessage('Missing required parameter for [Route: foo] [URI: foo/{one}/{two?}/{three?}] [Missing parameter: one].');
        $url->route('foo', $parameters);
    }

    /**
     * @return array
     */
    public function meaningfulMissingParameterMessageProvider() {
        return [
            [[], 'Missing required parameters for [Route: foo] [URI: foo/{one}/{two}/{three}/{four?}] [Missing parameters: one, two, three].'],
            [['one' => '123'], 'Missing required parameters for [Route: foo] [URI: foo/{one}/{two}/{three}/{four?}] [Missing parameters: two, three].'],
            [['two' => '123'], 'Missing required parameters for [Route: foo] [URI: foo/{one}/{two}/{three}/{four?}] [Missing parameters: one, three].'],
            [['one' => '123', 'two' => '123'], 'Missing required parameter for [Route: foo] [URI: foo/{one}/{two}/{three}/{four?}] [Missing parameter: three].'],
            [['two' => '123', 'three' => '123'], 'Missing required parameter for [Route: foo] [URI: foo/{one}/{two}/{three}/{four?}] [Missing parameter: one].'],
        ];
    }

    /**
     * @dataProvider meaningfulMissingParameterMessageProvider
     *
     * @param array  $parameters
     * @param string $expectedMessage
     */
    public function testUrlGenerationNamesTheMissingParameters($parameters, $expectedMessage) {
        $url = $this->generator('http://www.foo.com:8080/');
        $url->routes->add($this->route(['GET'], 'foo/{one}/{two}/{three}/{four?}', ['as' => 'foo', function () {
        }]));

        $this->expectException(CRouting_Exception_UrlGenerationException::class);
        $this->expectExceptionMessage($expectedMessage);
        $url->route('foo', $parameters);
    }

    public function testForceRootUrl() {
        $url = $this->generator();
        $url->forceRootUrl('https://www.bar.com');
        $this->assertSame('http://www.bar.com/foo/bar', $url->to('foo/bar'), 'skema tetap mengikuti request, root yang diganti');

        $url->forceRootUrl('http://www.foo.com/');
        $this->assertSame('http://www.foo.com/bar', $url->to('/bar'));

        $url = $this->generator();
        $url->forceScheme('https');
        $url->routes->add($this->route(['GET'], '/foo', ['as' => 'plain']));
        $this->assertSame('https://www.foo.com/foo', $url->route('plain'));

        $url->forceRootUrl('https://www.bar.com');
        $this->assertSame('https://www.bar.com/foo', $url->route('plain'));

        $url->forceRootUrl(null);
        $this->assertSame('https://www.foo.com/foo', $url->route('plain'));
    }

    public function testForceHttps() {
        $url = $this->generator();
        $url->forceHttps();
        $url->routes->add($this->route(['GET'], '/foo', ['as' => 'plain']));

        $this->assertSame('https://www.foo.com/foo', $url->route('plain'));
        $this->assertSame('https://www.foo.com/x', $url->to('x'));

        $url->forceScheme(null);
        $this->assertSame('http://www.foo.com/x', $url->to('x'));
    }

    public function testFormatSchemeHonoursTheExplicitFlagFirst() {
        $url = $this->generator();

        $this->assertSame('http://', $url->formatScheme());
        $this->assertSame('https://', $url->formatScheme(true));
        $this->assertSame('http://', $url->formatScheme(false));
        $url->forceScheme('https');
        $this->assertSame('https://', $url->formatScheme());
        $this->assertSame('http://', $url->formatScheme(false));
    }

    public function testFullAndCurrent() {
        $url = $this->generator('http://www.foo.com/foo/bar?x=1');

        $this->assertSame('http://www.foo.com/foo/bar?x=1', $url->full());
        $this->assertSame('http://www.foo.com/foo/bar', $url->current());
    }

    public function testPrevious() {
        $url = $this->generator();
        $url->setSessionResolver(function () {
            return null;
        });

        $url->getRequest()->headers->set('referer', 'http://www.bar.com/');
        $this->assertSame('http://www.bar.com/', $url->previous());

        $url->getRequest()->headers->remove('referer');
        $this->assertSame($url->to('/'), $url->previous());
        $this->assertSame($url->to('/foo'), $url->previous('/foo'));
    }

    public function testPreviousFallsBackToTheSession() {
        $url = $this->generator();
        $url->setSessionResolver(function () {
            return new UrlGeneratorTestSession('http://www.foo.com/dari-sesi');
        });

        $this->assertSame('http://www.foo.com/dari-sesi', $url->previous());
        $this->assertSame('http://www.foo.com/dari-sesi', $url->previous('/fallback'), 'sesi menang atas fallback');
    }

    public function testRouteNotDefinedException() {
        $url = $this->generator();

        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessage('Route [not_exists_route] not defined.');
        $url->route('not_exists_route');
    }

    public function testRouteParametersContainingPercentSignsAreEncoded() {
        $url = $this->generator();
        $url->routes->add($this->route(['GET'], 'foo/{bar}', ['as' => 'foo', function () {
        }]));

        //tanda persen mentah akan didekode lagi saat router mencocokkan URL, jadi nilainya harus
        //selamat melewati rawurldecode()
        $this->assertSame('http://www.foo.com/foo/%2566oo', $url->route('foo', ['bar' => '%66oo']));
        $this->assertSame('http://www.foo.com/foo/100%25', $url->route('foo', ['bar' => '100%']));
        $this->assertSame('%66oo', rawurldecode('%2566oo'));
        $this->assertSame('100%', rawurldecode('100%25'));

        $this->assertSame('http://www.foo.com/foo/a%3Fb%23c', $url->route('foo', ['bar' => 'a?b#c']));
        $this->assertSame('http://www.foo.com/foo/bar', $url->route('foo', ['bar' => 'bar']));
        $this->assertSame('http://www.foo.com/foo/1', $url->route('foo', ['bar' => 1]));
    }

    public function testEncodedRouteParametersAreNotEncodedAgain() {
        $url = $this->generator();
        $url->routes->add($this->route(['GET'], 'foo/{bar}', ['as' => 'foo', function () {
        }]));

        //nilai yang sudah di-encode pemanggil ditandai supaya dipakai apa adanya
        $this->assertSame('http://www.foo.com/foo/foo%20bar', $url->route('foo', ['bar' => new CRouting_EncodedParameter('foo%20bar')]));
        $this->assertSame('http://www.foo.com/foo/%66oo', $url->route('foo', ['bar' => new CRouting_EncodedParameter('%66oo')]));
        $this->assertSame('http://www.foo.com/foo/foo%2520bar', $url->route('foo', ['bar' => 'foo%20bar']));
        $this->assertSame('x%2F', (string) new CRouting_EncodedParameter('x%2F'));
    }

    public function testSignedUrl() {
        $url = $this->generator();
        $url->setKeyResolver(function () {
            return 'secret';
        });
        $url->routes->add($this->route(['GET'], 'foo', ['as' => 'foo', function () {
        }]));

        $request = CHTTP_Request::create($url->signedRoute('foo'));
        $this->assertTrue($url->hasValidSignature($request));
        $this->assertTrue($url->hasCorrectSignature($request));
        $this->assertTrue($url->signatureHasNotExpired($request));

        $request = CHTTP_Request::create($url->signedRoute('foo') . '&tampered=true');
        $this->assertFalse($url->hasValidSignature($request));

        $url->setKeyResolver(function () {
            return 'kunci-lain';
        });
        $this->assertFalse($url->hasValidSignature(CHTTP_Request::create($url->signedRoute('foo'))) === false, 'kunci baru memvalidasi tanda tangannya sendiri');
    }

    public function testSignedUrlIsRejectedWithAnotherKey() {
        $url = $this->generator();
        $url->setKeyResolver(function () {
            return 'secret';
        });
        $url->routes->add($this->route(['GET'], 'foo', ['as' => 'foo']));
        $signed = $url->signedRoute('foo');

        $url->setKeyResolver(function () {
            return 'other';
        });
        $this->assertFalse($url->hasValidSignature(CHTTP_Request::create($signed)));
    }

    public function testSignedUrlImplicitModelBinding() {
        $url = $this->generator();
        $url->setKeyResolver(function () {
            return 'secret';
        });
        $url->routes->add($this->route(['GET'], 'foo/{user:slug}', ['as' => 'foo', function () {
        }]));
        $user = new UrlGeneratorTestRoutable();
        $user->key = 'abai';

        $signed = $url->signedRoute('foo', $user);
        $this->assertStringContainsString('/foo/test-slug?signature=', $signed);
        $this->assertTrue($url->hasValidSignature(CHTTP_Request::create($signed)));
    }

    public function testSignedRelativeUrl() {
        $url = $this->generator();
        $url->setKeyResolver(function () {
            return 'secret';
        });
        $url->routes->add($this->route(['GET'], 'foo', ['as' => 'foo', function () {
        }]));

        $result = $url->signedRoute('foo', [], null, false);
        $this->assertStringStartsWith('/foo?signature=', $result);
        $this->assertTrue($url->hasValidSignature(CHTTP_Request::create($result), false));
        $this->assertTrue($url->hasValidRelativeSignature(CHTTP_Request::create($result)));

        $request = CHTTP_Request::create($url->signedRoute('foo', [], null, false) . '?tampered=true');
        $this->assertFalse($url->hasValidSignature($request, false));
    }

    public function testTemporarySignedUrlExpires() {
        $url = $this->generator();
        $url->setKeyResolver(function () {
            return 'secret';
        });
        $url->routes->add($this->route(['GET'], 'foo', ['as' => 'foo']));

        $valid = CHTTP_Request::create($url->temporarySignedRoute('foo', 3600));
        $this->assertTrue($url->hasValidSignature($valid));
        $this->assertGreaterThan(time(), (int) $valid->query('expires'));

        CCarbon::setTestNow(CCarbon::now()->addSeconds(3601));

        try {
            $this->assertTrue($url->hasCorrectSignature($valid), 'tanda tangan tetap benar');
            $this->assertFalse($url->signatureHasNotExpired($valid));
            $this->assertFalse($url->hasValidSignature($valid));
        } finally {
            CCarbon::setTestNow();
        }
    }

    public function testSignedUrlParameterCannotBeNamedSignature() {
        $url = $this->generator();
        $url->routes->add($this->route(['GET'], 'foo/{signature}', ['as' => 'foo']));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reserved');
        $url->signedRoute('foo', ['signature' => 'bar']);
    }

    public function testPassedParametersHavePrecedenceOverDefaults() {
        $url = $this->generator('https://www.foo.com/');
        $url->defaults(['tenant' => 'defaultTenant']);
        $url->routes->add($this->route(['GET'], 'bar/{tenant}/{post}', ['as' => 'bar', function () {
        }]));

        $tenant = new UrlGeneratorTestRoutable();
        $tenant->key = 'concreteTenant';
        $post = new UrlGeneratorTestRoutable();
        $post->key = 'concretePost';

        $this->assertSame('https://www.foo.com/bar/concreteTenant/concretePost', $url->route('bar', ['tenant' => $tenant, 'post' => $post]));
        $this->assertSame('https://www.foo.com/bar/concreteTenant/concretePost', $url->route('bar', [$tenant, $post]));
        $this->assertSame(['tenant' => 'defaultTenant'], $url->getDefaultParameters());
    }

    public function testComplexRouteGenerationWithDefaultsAndMixedParameterSyntax() {
        $url = $this->generator('https://www.foo.com/');
        $url->defaults(['tenant' => 'defaultTenant', 'user' => 'defaultUser']);
        $url->routes->add($this->route(['GET'], 'tenantPostUser/{tenant}/{post}/{user}', ['as' => 'tenantPostUser']));
        $url->routes->add($this->route(['GET'], 'tenantPostCommentUser/{tenant}/{post}/{comment}/{user}', ['as' => 'tenantPostCommentUser']));

        //post lewat kunci, posisional jatuh ke user
        $this->assertSame('https://www.foo.com/tenantPostUser/defaultTenant/concretePost/concreteUser', $url->route('tenantPostUser', ['post' => 'concretePost', 'concreteUser']));

        $base = 'https://www.foo.com/tenantPostCommentUser/defaultTenant/concretePost/concreteComment/';
        $this->assertSame($base . 'defaultUser', $url->route('tenantPostCommentUser', ['post' => 'concretePost', 'concreteComment']));
        $this->assertSame($base . 'defaultUser', $url->route('tenantPostCommentUser', ['concretePost', 'comment' => 'concreteComment']));
        $this->assertSame($base . 'defaultUser', $url->route('tenantPostCommentUser', ['comment' => 'concreteComment', 'concretePost']));
        $this->assertSame($base . 'concreteUser', $url->route('tenantPostCommentUser', ['post' => 'concretePost', 'comment' => 'concreteComment', 'concreteUser']));
        $this->assertSame($base . 'concreteUser', $url->route('tenantPostCommentUser', ['post' => 'concretePost', 'concreteComment', 'concreteUser']));
        $this->assertSame($base . 'concreteUser', $url->route('tenantPostCommentUser', ['concretePost', 'comment' => 'concreteComment', 'concreteUser']));

        $full = 'https://www.foo.com/tenantPostCommentUser/concreteTenant/concretePost/concreteComment/concreteUser';
        $this->assertSame($full, $url->route('tenantPostCommentUser', ['concreteTenant', 'post' => 'concretePost', 'comment' => 'concreteComment', 'concreteUser']));
        $this->assertSame($full, $url->route('tenantPostCommentUser', ['post' => 'concretePost', 'comment' => 'concreteComment', 'concreteTenant', 'concreteUser']));
    }

    public function testDefaultsCanBeCombinedWithExtraQueryParameters() {
        $url = $this->generator('https://www.foo.com/');
        $url->defaults(['tenant' => 'defaultTenant', 'tenant:slug' => 'defaultTenantSlug', 'user' => 'defaultUser']);
        $url->routes->add($this->route(['GET'], 'tenantPost/{tenant}/{post}', ['as' => 'tenantPost']));
        $url->routes->add($this->route(['GET'], 'tenantSlugPost/{tenant:slug}/{post}', ['as' => 'tenantSlugPost']));
        $slug = new UrlGeneratorTestRoutable();
        $slug->slug = 'concreteTenantSlug';

        $this->assertSame('https://www.foo.com/tenantPost/concreteTenant/concretePost?extraQuery', $url->route('tenantPost', ['concreteTenant', 'concretePost', 'extraQuery']));
        $this->assertSame('https://www.foo.com/tenantPost/concreteTenant/concretePost?extra=query&extraQuery', $url->route('tenantPost', ['concreteTenant', 'concretePost', 'extraQuery', 'extra' => 'query']));
        $this->assertSame('https://www.foo.com/tenantPost/defaultTenant/concretePost?extra=query', $url->route('tenantPost', ['concretePost', 'extra' => 'query']));
        $this->assertSame('https://www.foo.com/tenantPost/concreteTenant/concretePost?extra=query', $url->route('tenantPost', ['concreteTenant', 'extra' => 'query', 'concretePost']));

        $this->assertSame('https://www.foo.com/tenantSlugPost/concreteTenantSlug/concretePost?extraQuery', $url->route('tenantSlugPost', [$slug, 'concretePost', 'extraQuery']));
        $this->assertSame('https://www.foo.com/tenantSlugPost/defaultTenantSlug/concretePost', $url->route('tenantSlugPost', ['concretePost']));
    }

    public function testDefaultsFillAMissingRouteParameter() {
        $url = $this->generator('https://www.foo.com/');
        $url->defaults(['tenant' => 'defaultTenant']);
        $url->routes->add($this->route(['GET'], 'bar/{tenant}/{post}', ['as' => 'bar']));

        $this->assertSame('https://www.foo.com/bar/defaultTenant/x', $url->route('bar', ['post' => 'x']));
    }

    public function testDefaultsSurviveSetRequest() {
        $url = $this->generator();
        $url->defaults(['locale' => 'id']);
        $url->setRequest(CHTTP_Request::create('http://www.bar.com/'));

        $this->assertSame(['locale' => 'id'], $url->getDefaultParameters());
    }

    public function testIsValidUrl() {
        $url = $this->generator();

        $this->assertTrue($url->isValidUrl('http://example.com'));
        $this->assertTrue($url->isValidUrl('https://example.com/a?b=c'));
        $this->assertTrue($url->isValidUrl('//example.com'));
        $this->assertTrue($url->isValidUrl('mailto:a@b.c'));
        $this->assertTrue($url->isValidUrl('tel:+62'));
        $this->assertTrue($url->isValidUrl('#anchor'));
        $this->assertFalse($url->isValidUrl('foo/bar'));
        $this->assertFalse($url->isValidUrl('/foo/bar'));
        $this->assertFalse($url->isValidUrl(['http://example.com']));
    }
}

class UrlGeneratorTestGenerator extends CRouting_UrlGenerator {
    /**
     * @var CRouting_RouteCollection
     */
    public $routes;

    public function __construct() {
        parent::__construct();
        $this->routes = new CRouting_RouteCollection();
    }

    public function routes() {
        return $this->routes;
    }
}

class UrlGeneratorTestRoutable implements CRouting_UrlRoutableInterface {
    /**
     * @var string
     */
    public $key;

    /**
     * @var string
     */
    public $slug = 'test-slug';

    public function getRouteKey() {
        return $this->{$this->getRouteKeyName()};
    }

    public function getRouteKeyName() {
        return 'key';
    }

    public function resolveRouteBinding($value, $field = null) {
    }

    public function resolveChildRouteBinding($childType, $value, $field) {
    }
}

class UrlGeneratorTestInvokable {
    public function __invoke() {
        return 'hello';
    }
}

class UrlGeneratorTestSession {
    /**
     * @var string
     */
    private $previousUrl;

    public function __construct($previousUrl) {
        $this->previousUrl = $previousUrl;
    }

    public function previousUrl() {
        return $this->previousUrl;
    }
}
