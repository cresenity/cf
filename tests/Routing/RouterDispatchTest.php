<?php

use PHPUnit\Framework\TestCase;

/**
 * CRouting_Router - padanan suite hulu untuk dispatch: pencocokan rute, parameter, middleware
 * (closure, alias, grup), grup rute, binding parameter, OPTIONS/HEAD, dan bentuk respons.
 */
class RouterDispatchTest extends TestCase {
    /**
     * @return CRouting_Router
     */
    protected function getRouter() {
        return new CRouting_Router();
    }

    /**
     * @param CRouting_Router $router
     * @param string          $uri
     * @param string          $method
     *
     * @return string
     */
    protected function dispatch(CRouting_Router $router, $uri, $method = 'GET') {
        return $router->dispatch(CHTTP_Request::create($uri, $method))->getContent();
    }

    public function testBasicDispatchingOfRoutes() {
        $router = $this->getRouter();
        $router->get('foo/bar', function () {
            return 'hello';
        });
        $this->assertSame('hello', $this->dispatch($router, 'foo/bar'));

        $router = $this->getRouter();
        $router->get('foo/bar', function () {
            throw new CHTTP_Exception_ResponseException(new CHTTP_Response('hello'));
        });
        $this->assertSame('hello', $this->dispatch($router, 'foo/bar'), 'ResponseException menjadi respons');

        $router = $this->getRouter();
        $router->get('foo/bar', function () {
            return 'hello';
        });
        $router->post('foo/bar', function () {
            return 'post hello';
        });
        $this->assertSame('hello', $this->dispatch($router, 'foo/bar'));
        $this->assertSame('post hello', $this->dispatch($router, 'foo/bar', 'POST'));

        $router = $this->getRouter();
        $router->get('foo/bar', function () {
            return 'first';
        });
        $router->get('foo/bar', function () {
            return 'second';
        });
        $this->assertSame('second', $this->dispatch($router, 'foo/bar'), 'rute terakhir dengan uri sama menang');

        $router = $this->getRouter();
        $router->get('foo/bar/åαф', function () {
            return 'hello';
        });
        $this->assertSame('hello', $this->dispatch($router, 'foo/bar/%C3%A5%CE%B1%D1%84'));

        $router = $this->getRouter();
        $router->get('foo/bar', ['boom' => 'auth', function () {
            return 'closure';
        }]);
        $this->assertSame('closure', $this->dispatch($router, 'foo/bar'), 'kunci action asing diabaikan');
    }

    public function testDomainParametersAreDispatched() {
        $router = $this->getRouter();
        $router->get('foo/bar', ['domain' => 'api.{name}.bar', function ($name) {
            return $name;
        }]);
        $router->get('foo/bar', ['domain' => 'api.{name}.baz', function ($name) {
            return $name;
        }]);
        $this->assertSame('hery', $this->dispatch($router, 'http://api.hery.bar/foo/bar'));
        $this->assertSame('budi', $this->dispatch($router, 'http://api.budi.baz/foo/bar'));

        $router = $this->getRouter();
        $router->get('foo/{age}', ['domain' => 'api.{name}.bar', function ($name, $age) {
            return $name . $age;
        }]);
        $this->assertSame('hery25', $this->dispatch($router, 'http://api.hery.bar/foo/25'));
    }

    public function testRouteParametersArePassedByPositionNotName() {
        $router = $this->getRouter();
        $router->get('foo/{bar}', function ($name) {
            return $name;
        });
        $this->assertSame('hery', $this->dispatch($router, 'foo/hery'));

        $router = $this->getRouter();
        $router->get('foo/{bar}/{baz?}', function ($name, $age = 25) {
            return $name . $age;
        });
        $this->assertSame('hery25', $this->dispatch($router, 'foo/hery'));

        $router = $this->getRouter();
        $router->get('foo/{name}/boom/{age?}/{location?}', function ($name, $age = 25, $location = 'AR') {
            return $name . $age . $location;
        });
        $this->assertSame('hery30AR', $this->dispatch($router, 'foo/hery/boom/30'));
    }

    /**
     * @param string $uri
     * @param array  $action
     *
     * @return CRouting_Router
     */
    private function routerWithGet($uri, $action) {
        $router = $this->getRouter();
        $router->get($uri, $action);

        return $router;
    }

    public function testLeadingOptionalParameters() {
        //Route::bind() CF tidak mengikat ulang parameter yang sudah ada (lihat RouteTest), jadi
        //tiap request memakai router/rute baru
        $optionalAge = function ($age = 25) {
            return $age;
        };
        $nameAndAge = ['as' => 'foo', function ($name = 'hery', $age = 25) {
            return $name . $age;
        }];

        $this->assertSame('hery25', $this->dispatch($this->routerWithGet('{bar}/{baz?}', function ($name, $age = 25) {
            return $name . $age;
        }), 'hery'));

        $this->assertSame('25', $this->dispatch($this->routerWithGet('{baz?}', $optionalAge), '/'));
        $this->assertSame('30', $this->dispatch($this->routerWithGet('{baz?}', $optionalAge), '30'));

        $this->assertSame('hery25', $this->dispatch($this->routerWithGet('{foo?}/{baz?}', $nameAndAge), '/'));
        $this->assertSame('fred25', $this->dispatch($this->routerWithGet('{foo?}/{baz?}', $nameAndAge), 'fred'));
        $router = $this->routerWithGet('{foo?}/{baz?}', $nameAndAge);
        $this->assertSame('fred30', $this->dispatch($router, 'fred/30'));
        $this->assertTrue($router->currentRouteNamed('foo'));
        $this->assertTrue($router->currentRouteNamed('fo*'));
        $this->assertTrue($router->is('foo'));
        $this->assertTrue($router->is('foo', 'bar'));
        $this->assertFalse($router->is('bar'));
    }

    public function testEncodedSegmentsAreDecodedOnce() {
        $router = $this->getRouter();
        $router->get('foo/{file}', function ($file) {
            return $file;
        });

        $this->assertSame('oxygen%20', $this->dispatch($router, 'http://test.com/foo/oxygen%2520'));
    }

    public function testCurrentRouteNameAndAction() {
        $router = $this->getRouter();
        $router->patch('foo/bar', ['as' => 'foo', function () {
            return 'bar';
        }]);

        $this->assertSame('bar', $this->dispatch($router, 'foo/bar', 'PATCH'));
        $this->assertSame('foo', $router->currentRouteName());
        $this->assertSame('foo/bar', $router->current()->uri());
        $this->assertNull($router->currentRouteAction(), 'closure tidak punya controller');
        $this->assertInstanceOf(CHTTP_Request::class, $router->getCurrentRequest());
    }

    public function testHeadRequestsHaveNoBody() {
        $router = $this->getRouter();
        $router->get('foo/bar', function () {
            return 'hello';
        });
        $this->assertEmpty($this->dispatch($router, 'foo/bar', 'HEAD'));

        $router = $this->getRouter();
        $router->any('foo/bar', function () {
            return 'hello';
        });
        $this->assertEmpty($this->dispatch($router, 'foo/bar', 'HEAD'));
    }

    public function testClosureMiddleware() {
        $router = $this->getRouter();
        $middleware = function ($request, $next) {
            return 'caught';
        };
        $router->get('foo/bar', ['middleware' => $middleware, function () {
            return 'hello';
        }]);

        $this->assertSame('caught', $this->dispatch($router, 'foo/bar'));
    }

    public function testMiddlewareCanBeSkipped() {
        $router = $this->getRouter();
        $router->aliasMiddleware('web', RouterDispatchTestMiddlewareGroupTwo::class);
        $router->get('foo/bar', ['middleware' => 'web', function () {
            return 'hello';
        }])->withoutMiddleware(RouterDispatchTestMiddlewareGroupTwo::class);

        $this->assertSame('hello', $this->dispatch($router, 'foo/bar'));
    }

    public function testDefinedClosureMiddleware() {
        $router = $this->getRouter();
        $router->get('foo/bar', ['middleware' => 'foo', function () {
            return 'hello';
        }]);
        $router->aliasMiddleware('foo', function ($request, $next) {
            return 'caught';
        });

        $this->assertSame('caught', $this->dispatch($router, 'foo/bar'));
    }

    public function testMiddlewareRunsAroundTheRoute() {
        $router = $this->getRouter();
        $order = [];
        $router->aliasMiddleware('one', function ($request, $next) use (&$order) {
            $order[] = 'one-in';
            $response = $next($request);
            $order[] = 'one-out';

            return $response;
        });
        $router->get('foo/bar', ['middleware' => 'one', function () use (&$order) {
            $order[] = 'route';

            return 'hello';
        }]);

        $this->assertSame('hello', $this->dispatch($router, 'foo/bar'));
        $this->assertSame(['one-in', 'route', 'one-out'], $order);
    }

    public function testFluentRouting() {
        $router = $this->getRouter();
        $router->get('foo/bar')->uses(function () {
            return 'hello';
        });
        $this->assertSame('hello', $this->dispatch($router, 'foo/bar'));

        $router->post('foo/bar')->uses(function () {
            return 'hello';
        });
        $this->assertSame('hello', $this->dispatch($router, 'foo/bar', 'POST'));

        $router->get('foo/bar')->uses(function () {
            return 'middleware';
        })->middleware(RouterDispatchTestControllerMiddleware::class);
        $this->assertSame('middleware', $this->dispatch($router, 'foo/bar'));
        $this->assertContains(RouterDispatchTestControllerMiddleware::class, $router->getCurrentRoute()->middleware());
    }

    public function testRouteWithoutActionThrowsWhenRun() {
        $router = $this->getRouter();
        $route = $router->get('foo/bar');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Route for [foo/bar] has no action.');
        $route->bind(CHTTP_Request::create('foo/bar', 'GET'))->run();
    }

    public function testMiddlewareGroups() {
        $_SERVER['__middleware.group'] = false;
        $router = $this->getRouter();
        $router->get('foo/bar', ['middleware' => 'web', function () {
            return 'hello';
        }]);
        $router->aliasMiddleware('two', RouterDispatchTestMiddlewareGroupTwo::class);
        $router->middlewareGroup('web', [RouterDispatchTestMiddlewareGroupOne::class, 'two:hery']);

        $this->assertSame('caught hery', $this->dispatch($router, 'foo/bar'));
        $this->assertTrue($_SERVER['__middleware.group']);
        unset($_SERVER['__middleware.group']);
    }

    public function testMiddlewareGroupsCanReferenceOtherGroups() {
        $_SERVER['__middleware.group'] = false;
        $router = $this->getRouter();
        $router->get('foo/bar', ['middleware' => 'web', function () {
            return 'hello';
        }]);
        $router->aliasMiddleware('two', RouterDispatchTestMiddlewareGroupTwo::class);
        $router->middlewareGroup('first', ['two:budi']);
        $router->middlewareGroup('web', [RouterDispatchTestMiddlewareGroupOne::class, 'first']);

        $this->assertSame('caught budi', $this->dispatch($router, 'foo/bar'));
        $this->assertTrue($_SERVER['__middleware.group']);
        unset($_SERVER['__middleware.group']);
    }

    public function testMiddlewareGroupsCannotReferenceThemselves() {
        $router = $this->getRouter();
        $router->get('foo/bar', ['middleware' => 'web', function () {
            return 'hello';
        }]);
        $router->middlewareGroup('web', ['web']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('[web] middleware group is referencing itself.');
        $this->dispatch($router, 'foo/bar');
    }

    public function testGatherRouteMiddlewareResolvesAliasesAndGroups() {
        $router = $this->getRouter();
        $router->aliasMiddleware('two', RouterDispatchTestMiddlewareGroupTwo::class);
        $router->middlewareGroup('web', [RouterDispatchTestMiddlewareGroupOne::class, 'two:x']);
        $route = $router->get('foo/bar', ['middleware' => 'web', function () {
        }]);

        $this->assertSame([RouterDispatchTestMiddlewareGroupOne::class, RouterDispatchTestMiddlewareGroupTwo::class . ':x'], $router->gatherRouteMiddleware($route));
    }

    public function testFluentRouteNamingWithinAGroup() {
        $router = $this->getRouter();
        $router->group(['as' => 'foo.'], function () use ($router) {
            $router->get('bar', function () {
                return 'bar';
            })->name('bar');
        });

        $this->assertSame('bar', $this->dispatch($router, 'bar'));
        $this->assertSame('foo.bar', $router->currentRouteName());
    }

    public function testRouteGetAction() {
        $router = $this->getRouter();
        $route = $router->get('foo', function () {
            return 'foo';
        })->name('foo');

        $this->assertIsArray($route->getAction());
        $this->assertArrayHasKey('as', $route->getAction());
        $this->assertSame('foo', $route->getAction('as'));
        $this->assertNull($route->getAction('unknown_property'));
    }

    public function testClassesCanBeInjectedIntoRoutes() {
        $router = $this->getRouter();
        $injected = null;
        $router->get('foo/{var}', function (stdClass $foo, $var) use (&$injected) {
            $injected = func_get_args();

            return 'hello';
        });

        $this->assertSame('hello', $this->dispatch($router, 'foo/bar'));
        $this->assertInstanceOf(stdClass::class, $injected[0]);
        $this->assertSame('bar', $injected[1]);
    }

    /**
     * RouteCollection::match() CF mencoba routing controller berbasis berkas (CRouting_RouteFinder)
     * lebih dulu sebelum memeriksa verb lain, jadi respons OPTIONS otomatis, MethodNotAllowed, dan
     * NotFound tidak bisa diuji lewat dispatch() di CLI; yang diuji di sini pencocokan verb-nya.
     */
    public function testHeadIsServedByGetRoutesOnly() {
        $router = $this->getRouter();
        $router->match(['GET', 'POST'], 'foo', function () {
            return 'bar';
        });
        $response = $router->dispatch(CHTTP_Request::create('foo', 'HEAD'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertEmpty($response->getContent());

        $route = new CRouting_Route(['POST'], 'foo', function () {
        });
        $this->assertFalse($route->matches(CHTTP_Request::create('foo', 'HEAD')));
        $this->assertFalse($route->matches(CHTTP_Request::create('foo', 'GET')));
        $this->assertTrue($route->matches(CHTTP_Request::create('foo', 'POST')));
    }

    public function testRouteParametersDefaultValue() {
        $router = $this->getRouter();
        $router->get('foo/{bar?}', ['uses' => RouterDispatchTestControllerWithParameterStub::class . '@returnParameter'])->defaults('bar', 'foo');
        $this->assertSame('foo', $this->dispatch($router, 'foo'));

        $router->get('foo/{bar?}', ['uses' => RouterDispatchTestControllerWithParameterStub::class . '@returnParameter'])->defaults('bar', 'foo');
        $this->assertSame('bar', $this->dispatch($router, 'foo/bar'));

        $router->get('foo/{bar?}', function ($bar = '') {
            return $bar;
        })->defaults('bar', 'foo');
        $this->assertSame('foo', $this->dispatch($router, 'foo'));
    }

    public function testLeadingParamDoesNotReceiveForwardSlashOnEmptyPath() {
        $router = $this->getRouter();
        $outerOne = 'abc1234';
        $router->get('{one?}', [
            'uses' => function ($one = null) use (&$outerOne) {
                $outerOne = $one;

                return $one;
            },
            'where' => ['one' => '(.+)'],
        ]);

        $this->assertSame('', $this->dispatch($router, ''));
        $this->assertNull($outerOne);

        $route = $router->getRoutes()->getRoutes()[0];
        $this->assertSame('foo', $this->dispatch($this->routerWithGet('{one?}', $route->getAction()), '/foo'));
        $this->assertSame('foo/bar/baz', $this->dispatch($this->routerWithGet('{one?}', $route->getAction()), '/foo/bar/baz'));
    }

    public function testRoutesDoNotMatchNonMatchingPathsWithLeadingOptionals() {
        $route = new CRouting_Route('GET', '{baz?}', function ($age = 25) {
            return $age;
        });

        $this->assertTrue($route->matches(CHTTP_Request::create('30', 'GET')));
        $this->assertFalse($route->matches(CHTTP_Request::create('foo/bar', 'GET')));
    }

    public function testRoutesDoNotMatchNonMatchingDomain() {
        $route = new CRouting_Route('GET', 'foo/bar', ['domain' => 'api.foo.bar', function () {
            return 'hello';
        }]);

        $this->assertTrue($route->matches(CHTTP_Request::create('http://api.foo.bar/foo/bar', 'GET')));
        $this->assertFalse($route->matches(CHTTP_Request::create('http://api.baz.boom/foo/bar', 'GET')));
    }

    public function testRouteDomainRegistration() {
        $router = $this->getRouter();
        $router->get('/foo/bar')->domain('api.foo.bar')->uses(function () {
            return 'hello';
        });

        $this->assertSame('hello', $this->dispatch($router, 'http://api.foo.bar/foo/bar'));
    }

    public function testMatchesMethodAgainstRequests() {
        $route = new CRouting_Route('GET', 'foo/{bar}', function () {
        });
        $this->assertTrue($route->matches(CHTTP_Request::create('foo/bar', 'GET')));
        $this->assertFalse($route->matches(CHTTP_Request::create('foo/bar', 'POST')));
        $this->assertFalse((new CRouting_Route('GET', 'foo', function () {
        }))->matches(CHTTP_Request::create('foo/bar', 'GET')));

        $route = new CRouting_Route('GET', 'foo/{bar}', ['domain' => '{foo}.foo.com', function () {
        }]);
        $this->assertTrue($route->matches(CHTTP_Request::create('http://something.foo.com/foo/bar', 'GET')));
        $this->assertFalse($route->matches(CHTTP_Request::create('http://something.bar.com/foo/bar', 'GET')));

        $route = new CRouting_Route('GET', 'foo/{bar}', ['https', function () {
        }]);
        $this->assertTrue($route->matches(CHTTP_Request::create('https://foo.com/foo/bar', 'GET')));
        $this->assertFalse($route->matches(CHTTP_Request::create('http://foo.com/foo/bar', 'GET')));

        $route = new CRouting_Route('GET', 'foo/{bar}', ['https', 'baz' => true, function () {
        }]);
        $this->assertTrue($route->matches(CHTTP_Request::create('https://foo.com/foo/bar', 'GET')));

        $route = new CRouting_Route('GET', 'foo/{bar}', ['http', function () {
        }]);
        $this->assertFalse($route->matches(CHTTP_Request::create('https://foo.com/foo/bar', 'GET')));
        $this->assertTrue($route->matches(CHTTP_Request::create('http://foo.com/foo/bar', 'GET')));

        $route = new CRouting_Route('GET', 'foo/{bar}', ['baz' => true, function () {
        }]);
        $this->assertTrue($route->matches(CHTTP_Request::create('http://foo.com/foo/bar', 'GET')));
    }

    public function testWherePatternsProperlyFilter() {
        $route = (new CRouting_Route('GET', 'foo/{bar}', function () {
        }))->where('bar', '[0-9]+');
        $this->assertTrue($route->matches(CHTTP_Request::create('foo/123', 'GET')));
        $this->assertFalse($route->matches(CHTTP_Request::create('foo/123abc', 'GET')));

        //'where' di dalam action baru dipasang router (addWhereClausesToRoute); rute lepas memakai where()
        $route = (new CRouting_Route('GET', 'foo/{bar}', ['where' => ['bar' => '123|456'], function () {
        }]))->where('bar', '123|456');
        $this->assertTrue($route->matches(CHTTP_Request::create('foo/123', 'GET')));
        $this->assertFalse($route->matches(CHTTP_Request::create('foo/123abc', 'GET')));

        $route = (new CRouting_Route('GET', 'foo/{bar?}', function () {
        }))->where('bar', '[0-9]+');
        $this->assertTrue($route->matches(CHTTP_Request::create('foo/123', 'GET')));
        $this->assertTrue($route->matches(CHTTP_Request::create('foo', 'GET')));
        $this->assertFalse($route->matches(CHTTP_Request::create('foo/123abc', 'GET')));

        $route = (new CRouting_Route('GET', 'foo/{bar?}/{baz?}', function () {
        }))->where('bar', '[0-9]+');
        $this->assertTrue($route->matches(CHTTP_Request::create('foo/123', 'GET')));
        $this->assertTrue($route->matches(CHTTP_Request::create('foo/123/foo', 'GET')));

        $route = (new CRouting_Route('GET', '{subdomain}.awesome.test', function () {
        }))->whereIn('subdomain', ['one', 'two']);
        $this->assertFalse($route->matches(CHTTP_Request::create('test.awesome.test', 'GET')));
        $this->assertTrue($route->matches(CHTTP_Request::create('one.awesome.test', 'GET')));
    }

    public function testRoutePrefixParameterParsing() {
        $route = new CRouting_Route('GET', '/foo', ['prefix' => 'profiles/{user:username}/portfolios', 'uses' => function () {
        }]);

        $this->assertSame('profiles/{user}/portfolios/foo', $route->uri());
        $this->assertSame(['user' => 'username'], $route->bindingFields());
    }

    public function testDotDoesNotMatchEverything() {
        $route = new CRouting_Route('GET', 'images/{id}.{ext}', function () {
        });

        $request = CHTTP_Request::create('images/1.png', 'GET');
        $this->assertTrue($route->matches($request));
        $route->bind($request);
        $this->assertTrue($route->hasParameter('id'));
        $this->assertFalse($route->hasParameter('foo'));
        $this->assertSame('1', (string) $route->parameter('id'));
        $this->assertSame('png', $route->parameter('ext'));

        $route = new CRouting_Route('GET', 'images/{id}.{ext}', function () {
        });
        $request = CHTTP_Request::create('images/12.png', 'GET');
        $this->assertTrue($route->matches($request));
        $route->bind($request);
        $this->assertSame('12', $route->parameter('id'));

        $route = new CRouting_Route('GET', 'foo/{foo?}', function () {
        });
        $request = CHTTP_Request::create('foo', 'GET');
        $this->assertTrue($route->matches($request));
        $this->assertSame('bar', $route->bind($request)->parameter('foo', 'bar'));
    }

    public function testRouteBindingIsAppliedBySubstituteBindings() {
        //CF tidak punya middleware SubstituteBindings; substitusi dipanggil eksplisit lewat router
        $router = $this->getRouter();
        $router->bind('bar', function ($value) {
            return strtoupper($value);
        });
        $route = $router->get('foo/{bar}', function ($name) {
            return $name;
        });

        $bound = $route->bind(CHTTP_Request::create('foo/hery', 'GET'));
        $this->assertSame('hery', $bound->parameter('bar'));
        $router->substituteBindings($bound);
        $this->assertSame('HERY', $bound->parameter('bar'));
        $this->assertSame('hery', $bound->originalParameter('bar'));
        $this->assertSame(['bar' => 'hery'], $bound->originalParameters());
        $this->assertSame('HERY', $bound->run());
    }

    public function testRouteClassBinding() {
        $router = $this->getRouter();
        $router->bind('bar', RouterDispatchTestBindingStub::class);
        $route = $router->get('foo/{bar}', function ($name) {
            return $name;
        })->bind(CHTTP_Request::create('foo/hery', 'GET'));

        $this->assertSame('HERY', $router->substituteBindings($route)->parameter('bar'));

        $router->bind('bar', RouterDispatchTestBindingStub::class . '@find');
        $route = $router->get('foo/{bar}', function ($name) {
            return $name;
        })->bind(CHTTP_Request::create('foo/Dragon', 'GET'));

        $this->assertSame('dragon', $router->substituteBindings($route)->parameter('bar'));
    }

    public function testGroupMerging() {
        $old = ['prefix' => 'foo/bar/'];
        $this->assertEquals(['prefix' => 'foo/bar/baz', 'namespace' => null, 'where' => []], CRouting_RouteGroup::merge(['prefix' => 'baz'], $old));

        $old = ['domain' => 'foo'];
        $this->assertEquals(['domain' => 'baz', 'prefix' => null, 'namespace' => null, 'where' => []], CRouting_RouteGroup::merge(['domain' => 'baz'], $old));

        $old = ['as' => 'foo.'];
        $this->assertEquals(['as' => 'foo.bar', 'prefix' => null, 'namespace' => null, 'where' => []], CRouting_RouteGroup::merge(['as' => 'bar'], $old));

        $old = ['where' => ['var1' => 'foo', 'var2' => 'bar']];
        $this->assertEquals(['prefix' => null, 'namespace' => null, 'where' => ['var1' => 'foo', 'var2' => 'baz', 'var3' => 'qux']], CRouting_RouteGroup::merge(['where' => ['var2' => 'baz', 'var3' => 'qux']], $old));

        $this->assertEquals(['prefix' => null, 'namespace' => null, 'where' => ['var1' => 'foo', 'var2' => 'bar']], CRouting_RouteGroup::merge(['where' => ['var1' => 'foo', 'var2' => 'bar']], []));
    }

    public function testRouteGrouping() {
        $router = $this->getRouter();
        $router->group(['prefix' => 'foo'], function () use ($router) {
            $router->get('bar', function () {
                return 'hello';
            });
        });

        $this->assertSame('foo', $router->getRoutes()->getRoutes()[0]->getPrefix());
        $this->assertSame('hello', $this->dispatch($router, 'foo/bar'));
    }

    public function testRouteGroupingOutsideOfInheritedNamespace() {
        $router = $this->getRouter();
        $router->group(['namespace' => 'App\Http\Controllers'], function ($router) {
            $router->group(['namespace' => '\Foo\Bar'], function ($router) {
                $router->get('users', 'UsersController@index');
            });
        });

        $this->assertSame('Foo\Bar\UsersController@index', $router->getRoutes()->getRoutes()[0]->getAction()['uses']);
    }

    public function testRouteGroupingWithAs() {
        $router = $this->getRouter();
        $router->group(['prefix' => 'foo', 'as' => 'Foo::'], function () use ($router) {
            $router->get('bar', ['as' => 'bar', function () {
                return 'hello';
            }]);
        });

        $this->assertSame('foo/bar', $router->getRoutes()->getByName('Foo::bar')->uri());
    }

    public function testNestedRouteGroupingWithAs() {
        $router = $this->getRouter();
        $router->group(['prefix' => 'foo', 'as' => 'Foo::'], function () use ($router) {
            $router->group(['prefix' => 'bar', 'as' => 'Bar::'], function () use ($router) {
                $router->get('baz', ['as' => 'baz', function () {
                    return 'hello';
                }]);
            });
        });
        $this->assertSame('foo/bar/baz', $router->getRoutes()->getByName('Foo::Bar::baz')->uri());

        //lapisan tanpa "as": prefix registrar dipasang di depan prefix grup
        $router = $this->getRouter();
        $router->group(['prefix' => 'foo', 'as' => 'Foo::'], function () use ($router) {
            $router->group(['prefix' => 'bar'], function () use ($router) {
                $router->prefix('foz')->get('baz', ['as' => 'baz', function () {
                    return 'hello';
                }]);
            });
        });
        $this->assertSame('foz/foo/bar/baz', $router->getRoutes()->getByName('Foo::baz')->uri());
    }

    public function testNestedRouteGroupingPrefixing() {
        $router = $this->getRouter();
        $router->group(['prefix' => 'foo', 'as' => 'Foo::'], function () use ($router) {
            $router->prefix('bar')->get('baz', ['as' => 'baz', function () {
                return 'hello';
            }]);
        });

        $this->assertSame('bar/foo', $router->getRoutes()->getByName('Foo::baz')->getAction('prefix'));
    }

    public function testRoutePrefixing() {
        $router = $this->getRouter();
        $router->get('foo/bar', function () {
        });
        $routes = $router->getRoutes()->getRoutes();
        $routes[0]->prefix('prefix');
        $this->assertSame('prefix/foo/bar', $routes[0]->uri());

        $router = $this->getRouter();
        $router->get('foo/bar', function () {
        });
        $routes = $router->getRoutes()->getRoutes();
        $routes[0]->prefix('/');
        $this->assertSame('foo/bar', $routes[0]->uri());

        $router = $this->getRouter();
        $router->get('/', function () {
        });
        $routes = $router->getRoutes()->getRoutes();
        $routes[0]->prefix('prefix');
        $this->assertSame('prefix', $routes[0]->uri());

        $router = $this->getRouter();
        $router->get('/', function () {
        });
        $routes = $router->getRoutes()->getRoutes();
        $routes[0]->prefix('/');
        $this->assertSame('/', $routes[0]->uri());
    }

    public function testMergingControllerUses() {
        $router = $this->getRouter();
        $router->group(['namespace' => 'Namespace'], function () use ($router) {
            $router->get('foo/bar', 'Controller@action');
        });
        $this->assertSame('Namespace\Controller@action', $router->getRoutes()->getRoutes()[0]->getAction()['controller']);

        $router = $this->getRouter();
        $router->group(['namespace' => 'Namespace'], function () use ($router) {
            $router->group(['namespace' => 'Nested'], function () use ($router) {
                $router->get('foo/bar', 'Controller@action');
            });
        });
        $this->assertSame('Namespace\Nested\Controller@action', $router->getRoutes()->getRoutes()[0]->getAction()['controller']);

        $router = $this->getRouter();
        $router->group(['prefix' => 'baz'], function () use ($router) {
            $router->group(['namespace' => 'Namespace'], function () use ($router) {
                $router->get('foo/bar', 'Controller@action');
            });
        });
        $this->assertSame('Namespace\Controller@action', $router->getRoutes()->getRoutes()[0]->getAction()['controller']);
    }

    public function testInvalidActionException() {
        //kelas tanpa __invoke ditolak saat pendaftaran (hulu baru saat dispatch)
        $router = $this->getRouter();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid route action: [RouterDispatchTestControllerStub].');
        $router->get('/', ['uses' => RouterDispatchTestControllerStub::class]);
    }

    public function testRouterFiresRoutedEvent() {
        $router = $this->getRouter();
        $router->get('foo/bar', function () {
            return '';
        });
        $request = CHTTP_Request::create('http://foo.com/foo/bar', 'GET');
        $seen = [];
        $router->matched(function ($event) use (&$seen) {
            $seen[] = [$event->request, $event->route];
        });

        $router->dispatchToRoute($request);

        $this->assertNotEmpty($seen);
        list($seenRequest, $seenRoute) = end($seen);
        $this->assertSame($request, $seenRequest);
        $this->assertInstanceOf(CRouting_Route::class, $seenRoute);
        $this->assertSame('foo/bar', $seenRoute->uri());
        CEvent::dispatcher()->forget(CRouting_Event_RouteMatched::class);
    }

    public function testRouterPatternSetting() {
        $router = $this->getRouter();
        $router->pattern('test', 'pattern');
        $this->assertEquals(['test' => 'pattern'], $router->getPatterns());

        $router = $this->getRouter();
        $router->patterns(['test' => 'pattern', 'test2' => 'pattern2']);
        $this->assertEquals(['test' => 'pattern', 'test2' => 'pattern2'], $router->getPatterns());
    }

    public function testRouterPatternsApplyToNewRoutes() {
        $router = $this->getRouter();
        $router->pattern('id', '[0-9]+');
        $router->get('foo/{id}', function ($id) {
            return $id;
        });

        $this->assertSame('12', $this->dispatch($router, 'foo/12'));
        $route = $router->getRoutes()->getRoutes()[0];
        $this->assertSame(['id' => '[0-9]+'], $route->wheres);
        $this->assertFalse($route->matches(CHTTP_Request::create('foo/abc', 'GET')));
    }

    public function testControllerRouting() {
        $router = $this->getRouter();
        $router->get('foo/bar', RouterDispatchTestControllerStub::class . '@index');
        $this->assertSame('Hello World', $this->dispatch($router, 'foo/bar'));

        $router = $this->getRouter();
        $router->get('foo/bar', [RouterDispatchTestControllerStub::class, 'index']);
        $this->assertSame('Hello World', $this->dispatch($router, 'foo/bar'));
        $this->assertSame(RouterDispatchTestControllerStub::class . '@index', ltrim($router->currentRouteAction(), '\\'));
        $this->assertTrue($router->currentRouteUses(RouterDispatchTestControllerStub::class . '@index'));
        $this->assertTrue($router->uses('*ControllerStub@index'));
    }

    public function testCallableControllerRouting() {
        $router = $this->getRouter();
        $router->get('foo/bar', RouterDispatchTestControllerCallableStub::class . '@bar');
        $router->get('foo/baz', RouterDispatchTestControllerCallableStub::class . '@baz');

        $this->assertSame('bar', $this->dispatch($router, 'foo/bar'));
        $this->assertSame('baz', $this->dispatch($router, 'foo/baz'));
    }

    public function testDispatchingInvokableActionClasses() {
        $router = $this->getRouter();
        $router->get('foo/bar', RouterDispatchTestInvokableStub::class);

        $this->assertSame('invoked', $this->dispatch($router, 'foo/bar'));
    }

    public function testResponseIsReturned() {
        $router = $this->getRouter();
        $router->get('foo/bar', function () {
            return 'hello';
        });

        $response = $router->dispatch(CHTTP_Request::create('foo/bar', 'GET'));
        $this->assertInstanceOf(CHTTP_Response::class, $response);
        $this->assertNotInstanceOf(CHTTP_JsonResponse::class, $response);
        $this->assertStringStartsWith('text/html', $response->headers->get('Content-Type'));
    }

    public function testJsonResponseIsReturned() {
        $router = $this->getRouter();
        $router->get('foo/bar', function () {
            return ['foo', 'bar'];
        });

        $response = $router->dispatch(CHTTP_Request::create('foo/bar', 'GET'));
        $this->assertInstanceOf(CHTTP_JsonResponse::class, $response);
        $this->assertSame('["foo","bar"]', $response->getContent());
    }

    public function testResponsableAndResponseObjectsPassThrough() {
        $router = $this->getRouter();
        $router->get('responsable', function () {
            return new RouterDispatchTestResponsable();
        });
        $router->get('response', function () {
            return new CHTTP_Response('mentah', 201);
        });

        $this->assertSame('responsable', $this->dispatch($router, 'responsable'));
        $response = $router->dispatch(CHTTP_Request::create('response', 'GET'));
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('mentah', $response->getContent());
    }

    public function testRouteDefaultsFillMissingOptionalParameters() {
        $action = function ($bar = null) {
            return (string) $bar;
        };
        $router = $this->getRouter();
        $router->get('foo/{bar?}', $action)->setDefaults(['bar' => 'default']);
        $this->assertSame('default', $this->dispatch($router, 'foo'));

        $router = $this->getRouter();
        $router->get('foo/{bar?}', $action)->setDefaults(['bar' => 'default']);
        $this->assertSame('given', $this->dispatch($router, 'foo/given'));
    }
}

class RouterDispatchTestControllerStub {
    public function index() {
        return 'Hello World';
    }
}

class RouterDispatchTestControllerWithParameterStub {
    public function returnParameter($bar = '') {
        return $bar;
    }
}

class RouterDispatchTestControllerCallableStub {
    public function __call($method, $arguments = []) {
        return $method;
    }
}

class RouterDispatchTestInvokableStub {
    public function __invoke() {
        return 'invoked';
    }
}

class RouterDispatchTestControllerMiddleware {
    public function handle($request, $next) {
        return $next($request);
    }
}

class RouterDispatchTestMiddlewareGroupOne {
    public function handle($request, $next) {
        $_SERVER['__middleware.group'] = true;

        return $next($request);
    }
}

class RouterDispatchTestMiddlewareGroupTwo {
    public function handle($request, $next, $parameter = null) {
        return new CHTTP_Response('caught ' . $parameter);
    }
}

class RouterDispatchTestBindingStub {
    public function bind($value, $route) {
        return strtoupper($value);
    }

    public function find($value, $route) {
        return strtolower($value);
    }
}

class RouterDispatchTestResponsable implements CInterface_Responsable {
    public function toResponse($request) {
        return new CHTTP_Response('responsable');
    }
}
