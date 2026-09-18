<?php

use PHPUnit\Framework\TestCase;

/**
 * CRouting_RouteRegistrar - padanan suite hulu untuk pendaftaran rute fluent lewat router:
 * middleware, grup, prefix/nama/domain, batasan where, dan grup middleware. Router dibuat baru
 * per test supaya koleksi rute global tidak tersentuh.
 */
class RouteRegistrarTest extends TestCase {
    /**
     * @var CRouting_Router
     */
    protected $router;

    protected function setUp(): void {
        $this->router = new CRouting_Router();
    }

    /**
     * @return CRouting_Route
     */
    protected function getRoute() {
        $routes = $this->router->getRoutes()->get();

        return end($routes);
    }

    /**
     * @param string $middleware
     */
    protected function seeMiddleware($middleware) {
        $this->assertEquals($middleware, $this->getRoute()->middleware()[0]);
    }

    /**
     * @param string        $content
     * @param CHTTP_Request $request
     */
    protected function seeResponse($content, CHTTP_Request $request) {
        $route = $this->getRoute();

        $this->assertTrue($route->matches($request));
        $this->assertEquals($content, $route->bind($request)->run());
    }

    public function testMiddlewareFluentRegistration() {
        $this->router->middleware(['one', 'two'])->get('users', function () {
            return 'all-users';
        });
        $this->seeResponse('all-users', CHTTP_Request::create('users', 'GET'));
        $this->assertEquals(['one', 'two'], $this->getRoute()->middleware());

        $this->router->middleware('three', 'four')->get('users', function () {
            return 'all-users';
        });
        $this->seeResponse('all-users', CHTTP_Request::create('users', 'GET'));
        $this->assertEquals(['three', 'four'], $this->getRoute()->middleware());

        $this->router->get('users', function () {
            return 'all-users';
        })->middleware('five', 'six');
        $this->seeResponse('all-users', CHTTP_Request::create('users', 'GET'));
        $this->assertEquals(['five', 'six'], $this->getRoute()->middleware());

        $this->router->middleware('seven')->get('users', function () {
            return 'all-users';
        });
        $this->assertEquals(['seven'], $this->getRoute()->middleware());
    }

    public function testNullNamespaceIsRespected() {
        $this->router->middleware(['one'])->namespace(null)->get('users', function () {
            return 'all-users';
        });

        $this->assertNull($this->getRoute()->getAction()['namespace']);
    }

    public function testWithoutMiddlewareRegistration() {
        $this->router->middleware(['one', 'two'])->get('users', function () {
            return 'all-users';
        })->withoutMiddleware('one');

        $this->seeResponse('all-users', CHTTP_Request::create('users', 'GET'));
        $this->assertEquals(['one'], $this->getRoute()->excludedMiddleware());
    }

    public function testFallbackRoute() {
        $route = $this->router->fallback(function () {
            return 'fallback';
        });

        $this->assertTrue($route->isFallback);

        $route->setFallback(false);
        $this->assertFalse($route->isFallback);
        $route->setFallback(true);
        $this->assertTrue($route->isFallback);
    }

    public function testCanRegisterGetRouteWithClosureAction() {
        $this->router->middleware('get-middleware')->get('users', function () {
            return 'all-users';
        });

        $this->seeResponse('all-users', CHTTP_Request::create('users', 'GET'));
        $this->seeMiddleware('get-middleware');
    }

    public function testCanRegisterPostRouteWithClosureAction() {
        $this->router->middleware('post-middleware')->post('users', function () {
            return 'saved';
        });

        $this->seeResponse('saved', CHTTP_Request::create('users', 'POST'));
        $this->seeMiddleware('post-middleware');
    }

    public function testCanRegisterAnyRouteWithClosureAction() {
        $this->router->middleware('test-middleware')->any('users', function () {
            return 'anything';
        });

        $this->seeResponse('anything', CHTTP_Request::create('users', 'PUT'));
        $this->seeMiddleware('test-middleware');
    }

    public function testCanRegisterMatchRouteWithClosureAction() {
        $this->router->middleware('match-middleware')->match(['DELETE'], 'users', function () {
            return 'deleted';
        });

        $this->seeResponse('deleted', CHTTP_Request::create('users', 'DELETE'));
        $this->seeMiddleware('match-middleware');
    }

    public function testCanRegisterRouteWithArrayAndClosureAction() {
        $this->router->middleware('patch-middleware')->patch('users', [function () {
            return 'updated';
        }]);

        $this->seeResponse('updated', CHTTP_Request::create('users', 'PATCH'));
        $this->seeMiddleware('patch-middleware');
    }

    public function testCanRegisterRouteWithArrayAndClosureUsesAction() {
        $this->router->middleware('put-middleware')->put('users', ['uses' => function () {
            return 'replaced';
        }]);

        $this->seeResponse('replaced', CHTTP_Request::create('users', 'PUT'));
        $this->seeMiddleware('put-middleware');
    }

    public function testCanRegisterRouteWithControllerAction() {
        $this->router->middleware('controller-middleware')->get('users', RouteRegistrarControllerStub::class . '@index');

        $this->seeResponse('controller', CHTTP_Request::create('users', 'GET'));
        $this->seeMiddleware('controller-middleware');
    }

    public function testCanRegisterRouteWithControllerActionArray() {
        $this->router->middleware('controller-middleware')->get('users', [RouteRegistrarControllerStub::class, 'index']);

        $this->seeResponse('controller', CHTTP_Request::create('users', 'GET'));
        $this->seeMiddleware('controller-middleware');
    }

    public function testCanRegisterNamespacedGroupRouteWithControllerActionArray() {
        $this->router->group(['namespace' => 'WhatEver'], function () {
            $this->router->middleware('controller-middleware')->get('users', [RouteRegistrarControllerStub::class, 'index']);
        });
        $this->seeResponse('controller', CHTTP_Request::create('users', 'GET'));
        $this->seeMiddleware('controller-middleware');

        $this->router->group(['namespace' => 'WhatEver'], function () {
            $this->router->middleware('controller-middleware')->get('users', ['\\' . RouteRegistrarControllerStub::class, 'index']);
        });
        $this->seeResponse('controller', CHTTP_Request::create('users', 'GET'));
    }

    public function testCanRegisterRouteWithArrayAndControllerAction() {
        $this->router->middleware('controller-middleware')->put('users', [
            'uses' => RouteRegistrarControllerStub::class . '@index',
        ]);

        $this->seeResponse('controller', CHTTP_Request::create('users', 'PUT'));
        $this->seeMiddleware('controller-middleware');
    }

    public function testCanRegisterGroupWithMiddleware() {
        $this->router->middleware('group-middleware')->group(function ($router) {
            $router->get('users', function () {
                return 'all-users';
            });
        });

        $this->seeResponse('all-users', CHTTP_Request::create('users', 'GET'));
        $this->seeMiddleware('group-middleware');
    }

    public function testCanRegisterGroupWithoutMiddleware() {
        $this->router->withoutMiddleware('one')->group(function ($router) {
            $router->get('users', function () {
                return 'all-users';
            })->middleware(['one', 'two']);
        });

        $this->seeResponse('all-users', CHTTP_Request::create('users', 'GET'));
        $this->assertEquals(['one'], $this->getRoute()->excludedMiddleware());
    }

    public function testWithoutMiddlewareAccumulatesOnTheRegistrar() {
        $this->router->withoutMiddleware('one')->withoutMiddleware(['two', 'three'])->get('users', function () {
            return 'all-users';
        });

        $this->assertEquals(['one', 'two', 'three'], $this->getRoute()->excludedMiddleware());
    }

    public function testCanRegisterGroupWithNamespace() {
        $this->router->namespace('App\Http\Controllers')->group(function ($router) {
            $router->get('users', 'UsersController@index');
        });

        $this->assertSame('App\Http\Controllers\UsersController@index', $this->getRoute()->getAction()['uses']);
    }

    public function testRedirectAndViewRoutesUseTheFrameworkControllers() {
        $this->router->namespace('App\Http\Controllers')->group(function ($router) {
            $router->redirect('users', '/');
        });
        $this->assertSame('CController_RedirectController@__invoke', ltrim($this->getRoute()->getAction()['uses'], '\\'));
        $this->assertSame('/', $this->getRoute()->defaults['destination']);
        $this->assertSame(302, $this->getRoute()->defaults['status']);

        $this->router->permanentRedirect('old', '/new');
        $this->assertSame(301, $this->getRoute()->defaults['status']);

        $this->router->view('welcome', 'pages.welcome', ['a' => 1]);
        $this->assertSame('CController_ViewController@__invoke', ltrim($this->getRoute()->getAction()['uses'], '\\'));
        $this->assertSame(['GET', 'HEAD'], $this->getRoute()->methods());
        $this->assertSame(['view' => 'pages.welcome', 'data' => ['a' => 1], 'status' => 200, 'headers' => []], $this->getRoute()->defaults);
    }

    public function testCanRegisterGroupWithPrefix() {
        $this->router->prefix('api')->group(function ($router) {
            $router->get('users', 'UsersController@index');
        });

        $this->assertSame('api/users', $this->getRoute()->uri());
    }

    public function testCanRegisterGroupWithPrefixAndWhere() {
        $this->router->prefix('foo/{bar}')->where(['bar' => '[0-9]+'])->group(function ($router) {
            $router->get('here', function () {
                return 'good';
            });
        });

        $this->seeResponse('good', CHTTP_Request::create('foo/12345/here', 'GET'));
        $this->assertFalse($this->getRoute()->matches(CHTTP_Request::create('foo/abc/here', 'GET')));
    }

    public function testCanRegisterGroupWithNamePrefix() {
        $this->router->name('api.')->group(function ($router) {
            $router->get('users', 'UsersController@index')->name('users');
        });

        $this->assertSame('api.users', $this->getRoute()->getName());
    }

    public function testCanRegisterGroupWithDomain() {
        $this->router->domain('{account}.myapp.com')->group(function ($router) {
            $router->get('users', 'UsersController@index');
        });

        $this->assertSame('{account}.myapp.com', $this->getRoute()->getDomain());
    }

    public function testCanRegisterGroupWithDomainAndNamePrefix() {
        $this->router->domain('{account}.myapp.com')->name('api.')->group(function ($router) {
            $router->get('users', 'UsersController@index')->name('users');
        });

        $this->assertSame('{account}.myapp.com', $this->getRoute()->getDomain());
        $this->assertSame('api.users', $this->getRoute()->getName());
    }

    public function testRouteGroupingWithoutPrefix() {
        $this->router->group([], function ($router) {
            $router->prefix('bar')->get('baz', ['as' => 'baz', function () {
                return 'hello';
            }]);
        });

        $this->seeResponse('hello', CHTTP_Request::create('bar/baz', 'GET'));
    }

    public function testNestedGroupsStackPrefixesNamesAndMiddleware() {
        $this->router->prefix('api')->name('api.')->middleware('a')->group(function ($router) {
            $router->prefix('v1')->name('v1.')->middleware('b')->group(function ($router) {
                $router->get('users', function () {
                    return 'nested';
                })->name('users');
            });
        });

        $route = $this->getRoute();
        $this->assertSame('api/v1/users', $route->uri());
        $this->assertSame('api.v1.users', $route->getName());
        $this->assertEquals(['a', 'b'], $route->middleware());
        $this->seeResponse('nested', CHTTP_Request::create('api/v1/users', 'GET'));
    }

    public function testRouteGroupsCanBeDefinedTwice() {
        $this->router->group([], function ($router) {
            $router->get('foo', function () {
                return 'hello';
            });
        });
        $this->router->group([], function ($router) {
            $router->get('bar', function () {
                return 'goodbye';
            });
        });

        $routes = $this->router->getRoutes();
        $this->assertInstanceOf(CRouting_Route::class, $routes->match(CHTTP_Request::create('foo', 'GET')));
        $this->assertInstanceOf(CRouting_Route::class, $routes->match(CHTTP_Request::create('bar', 'GET')));
    }

    public function testRegisteringNonApprovedAttributesThrows() {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Method CRouting_RouteRegistrar::unsupportedMethod does not exist.');

        $this->router->domain('foo')->unsupportedMethod('bar')->group(function ($router) {
        });
    }

    public function testAttributeRejectsAnUnknownKey() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Attribute [bogus] does not exist.');

        (new CRouting_RouteRegistrar($this->router))->attribute('bogus', 'x');
    }

    public function testWhereNumberRegistration() {
        $wheres = ['foo' => '[0-9]+', 'bar' => '[0-9]+'];
        $this->router->get('/{foo}/{bar}')->whereNumber(['foo', 'bar']);
        $this->router->get('/api/{bar}/{foo}')->whereNumber(['bar', 'foo']);

        foreach ($this->router->getRoutes() as $route) {
            $this->assertEquals($wheres, $route->wheres);
        }
    }

    public function testWhereAlphaRegistration() {
        $wheres = ['foo' => '[a-zA-Z]+', 'bar' => '[a-zA-Z]+'];
        $this->router->get('/{foo}/{bar}')->whereAlpha(['foo', 'bar']);
        $this->router->get('/api/{bar}/{foo}')->whereAlpha(['bar', 'foo']);

        foreach ($this->router->getRoutes() as $route) {
            $this->assertEquals($wheres, $route->wheres);
        }
    }

    public function testWhereAlphaNumericRegistration() {
        $this->router->get('/{foo}')->whereAlphaNumeric(['1a2b3c']);

        $this->assertEquals(['1a2b3c' => '[a-zA-Z0-9]+'], $this->getRoute()->wheres);
    }

    public function testWhereUlidAndUuidRegistration() {
        $this->router->get('/{foo}')->whereUlid('foo');
        $this->assertEquals(['foo' => '[0-7][0-9a-hjkmnp-tv-zA-HJKMNP-TV-Z]{25}'], $this->getRoute()->wheres);
        $this->assertTrue($this->getRoute()->matches(CHTTP_Request::create('/01ARZ3NDEKTSV4RRFFQ69G5FAV', 'GET')));
        $this->assertFalse($this->getRoute()->matches(CHTTP_Request::create('/bukan-ulid', 'GET')));

        $this->router->get('/{bar}')->whereUuid('bar');
        $this->assertTrue($this->getRoute()->matches(CHTTP_Request::create('/a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', 'GET')));
        $this->assertFalse($this->getRoute()->matches(CHTTP_Request::create('/bukan-uuid', 'GET')));
    }

    public function testWhereInRegistration() {
        $wheres = ['foo' => 'one|two', 'bar' => 'one|two'];
        $this->router->get('/{foo}/{bar}')->whereIn(['foo', 'bar'], ['one', 'two']);
        $this->router->get('/api/{bar}/{foo}')->whereIn(['bar', 'foo'], ['one', 'two']);

        foreach ($this->router->getRoutes() as $route) {
            $this->assertEquals($wheres, $route->wheres);
        }
        $this->assertTrue($this->getRoute()->matches(CHTTP_Request::create('/api/one/two', 'GET')));
        $this->assertFalse($this->getRoute()->matches(CHTTP_Request::create('/api/three/two', 'GET')));
    }

    public function testGroupWhereNumberRegistrationOnRouter() {
        $wheres = ['foo' => '[0-9]+', 'bar' => '[0-9]+'];
        $this->router->whereNumber(['foo', 'bar'])->prefix('/{foo}/{bar}')->group(function ($router) {
            $router->get('/');
        });
        $this->router->whereNumber(['bar', 'foo'])->prefix('/api/{bar}/{foo}')->group(function ($router) {
            $router->get('/');
        });

        foreach ($this->router->getRoutes() as $route) {
            $this->assertEquals($wheres, $route->wheres);
        }
    }

    public function testGroupWhereInRegistrationOnRouteRegistrar() {
        $this->router->prefix('/{foo}')->whereIn(['foo'], ['one', 'two'])->group(function ($router) {
            $router->get('/', function () {
                return 'ok';
            });
        });

        $this->assertEquals(['foo' => 'one|two'], $this->getRoute()->wheres);
        $this->seeResponse('ok', CHTTP_Request::create('/one', 'GET'));
    }

    public function testCanSetRouteName() {
        $this->router->as('users.index')->get('users', function () {
            return 'all-users';
        });
        $this->seeResponse('all-users', CHTTP_Request::create('users', 'GET'));
        $this->assertSame('users.index', $this->getRoute()->getName());

        $this->router->name('users.other')->get('users', function () {
            return 'all-users';
        });
        $this->assertSame('users.other', $this->getRoute()->getName());
    }

    public function testCanSetRouteNameWithNonCallableControllerActionArray() {
        $this->router->name('users.missing')->get('users', [RouteRegistrarControllerStub::class, 'missing']);

        $this->assertSame('users.missing', $this->getRoute()->getName());
        $this->assertSame(RouteRegistrarControllerStub::class . '@missing', ltrim($this->getRoute()->getAction('uses'), '\\'));
    }

    public function testNamedRoutesAreFoundInTheCollection() {
        $this->router->get('users', function () {
        })->name('users.index');

        //nama yang diberikan sesudah pendaftaran baru terlihat setelah lookup disegarkan
        $this->assertFalse($this->router->getRoutes()->hasNamedRoute('users.index'));
        $this->router->getRoutes()->refreshNameLookups();
        $this->assertTrue($this->router->getRoutes()->hasNamedRoute('users.index'));
        $this->assertTrue($this->router->has('users.index'));
        $this->assertFalse($this->router->has('users.missing'));
        $this->assertSame('users', $this->router->getRoutes()->getByName('users.index')->uri());
    }

    public function testPushMiddlewareToGroup() {
        $this->router->middlewareGroup('web', []);
        $this->router->pushMiddlewareToGroup('web', 'test-middleware');
        $this->assertEquals(['test-middleware'], $this->router->getMiddlewareGroups()['web']);

        $this->router->pushMiddlewareToGroup('api', 'test-middleware');
        $this->assertEquals(['test-middleware'], $this->router->getMiddlewareGroups()['api'], 'grup yang belum ada dibuat');

        $this->router->pushMiddlewareToGroup('api', 'test-middleware');
        $this->assertEquals(['test-middleware'], $this->router->getMiddlewareGroups()['api'], 'tidak digandakan');
    }

    public function testPrependMiddlewareToGroup() {
        $this->router->middlewareGroup('web', ['second']);
        $this->router->prependMiddlewareToGroup('web', 'first');

        $this->assertEquals(['first', 'second'], $this->router->getMiddlewareGroups()['web']);
        $this->assertTrue($this->router->hasMiddlewareGroup('web'));
        $this->assertFalse($this->router->hasMiddlewareGroup('api'));
    }

    public function testCanRemoveMiddlewareFromGroup() {
        $this->router->pushMiddlewareToGroup('web', 'test-middleware');
        $this->router->removeMiddlewareFromGroup('web', 'test-middleware');
        $this->assertSame([], $this->router->getMiddlewareGroups()['web']);

        $this->router->middlewareGroup('web', []);
        $this->router->removeMiddlewareFromGroup('web', 'different-test-middleware');
        $this->assertSame([], $this->router->getMiddlewareGroups()['web']);

        $this->router->removeMiddlewareFromGroup('missing', 'test-middleware');
        $this->assertArrayNotHasKey('missing', $this->router->getMiddlewareGroups());
    }

    public function testAliasMiddleware() {
        $this->router->aliasMiddleware('auth', 'RouteRegistrarMiddlewareStub');

        $this->assertSame('RouteRegistrarMiddlewareStub', $this->router->getMiddleware()['auth']);
    }
}

class RouteRegistrarControllerStub {
    public function index() {
        return 'controller';
    }
}
