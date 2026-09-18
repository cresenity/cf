<?php

/**
 * Description of Route.
 */
use Symfony\Component\Routing\Route as SymfonyRoute;

class CRouting_Route {
    use CRouting_Concern_CreatesRegularExpressionRouteConstraints,
        CRouting_Concern_RouteDependencyResolverTrait,
        CRouting_Concern_RouteOutputBufferRunner,
        CTrait_Macroable;

    /**
     * The URI pattern the route responds to.
     *
     * @var string
     */
    public $uri;

    /**
     * The HTTP methods the route responds to.
     *
     * @var array
     */
    public $methods;

    /**
     * The route action array.
     *
     * @var array
     */
    public $action;

    /**
     * Indicates whether the route is a fallback route.
     *
     * @var bool
     */
    public $isFallback = false;

    /**
     * The controller instance.
     *
     * @var mixed
     */
    public $controller;

    /**
     * The default values for the route.
     *
     * @var array
     */
    public $defaults = [];

    /**
     * The regular expression requirements.
     *
     * @var array
     */
    public $wheres = [];

    /**
     * The array of matched parameters.
     *
     * @var null|array
     */
    public $parameters;

    /**
     * The parameter names for the route.
     *
     * @var null|array
     */
    public $parameterNames;

    /**
     * The computed gathered middleware.
     *
     * @var null|array
     */
    public $computedMiddleware;

    /**
     * The compiled version of the route.
     *
     * @var \Symfony\Component\Routing\CompiledRoute
     */
    public $compiled;

    /**
     * The validators used by the routes.
     *
     * @var array
     */
    public static $validators;

    /**
     * The array of the matched parameters' original values.
     *
     * @var array
     */
    protected $originalParameters;

    /**
     * Indicates the maximum number of seconds the route should acquire a session lock for.
     *
     * @var null|int
     */
    protected $lockSeconds;

    /**
     * Indicates the maximum number of seconds the route should wait while attempting to acquire a session lock.
     *
     * @var null|int
     */
    protected $waitSeconds;

    /**
     * The router instance used by the route.
     *
     * @var CRouting_Router
     */
    protected $router;

    /**
     * The container instance used by the route.
     *
     * @var CContainer_Container
     */
    protected $container;

    /**
     * The fields that implicit binding should use for a given parameter.
     *
     * @var array
     */
    protected $bindingFields = [];

    /**
     * @var CRouting_RouteData
     */
    protected $routeData;

    /**
     * Create a new Route instance.
     *
     * @param array|string   $methods
     * @param string         $uri
     * @param \Closure|array $action
     * @param mixed          $parameters
     *
     * @return void
     */
    public function __construct($methods, $uri, $action, $parameters = null) {
        $this->uri = $uri;
        $this->methods = (array) $methods;
        $this->action = carr::except($this->parseAction($action), ['prefix']);

        if (in_array('GET', $this->methods) && !in_array('HEAD', $this->methods)) {
            $this->methods[] = 'HEAD';
        }

        $this->prefix(is_array($action) ? carr::get($action, 'prefix') : '');

        if ($parameters != null) {
            $this->parameters = $parameters;
        }
    }

    /**
     * Parse the route action into a standard array.
     *
     * @param null|callable|array $action
     *
     * @throws \UnexpectedValueException
     *
     * @return array
     */
    protected function parseAction($action) {
        return CRouting_RouteAction::parse($this->uri, $action);
    }

    /**
     * Run the route action and return the response.
     *
     * @return mixed
     */
    public function run() {
        try {
            if ($this->isControllerAction()) {
                return $this->runController();
            }

            return $this->runCallable();
        } catch (CHTTP_Exception_ResponseException $e) {
            return $e->getResponse();
        }
    }

    /**
     * Checks whether the route's action is a controller.
     *
     * @return bool
     */
    protected function isControllerAction() {
        return is_string($this->action['uses']) && !$this->isSerializedClosure();
    }

    /**
     * Run the route action and return the response.
     *
     * @return mixed
     */
    protected function runCallable() {
        $callable = $this->action['uses'];

        if ($this->isSerializedClosure()) {
            $callable = unserialize($this->action['uses'])->getClosure();
        }

        return $callable(...array_values($this->resolveMethodDependencies(
            $this->parametersWithoutNulls(),
            new ReflectionFunction($callable)
        )));
    }

    /**
     * Determine if the route action is a serialized Closure.
     *
     * @return bool
     */
    protected function isSerializedClosure() {
        return CRouting_RouteAction::containsSerializedClosure($this->action);
    }

    /**
     * Run the route action and return the response.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     *
     * @return mixed
     */
    protected function runController() {
        return $this->controllerDispatcher()->dispatch(
            $this,
            $this->getController(),
            $this->getControllerMethod()
        );
    }

    /**
     * Get the controller instance for the route.
     *
     * @return mixed
     */
    public function getController() {
        if (!$this->controller) {
            $class = $this->parseControllerCallback()[0];

            try {
                $this->controller = CContainer::getInstance()->make(ltrim($class, '\\'));
            } catch (Exception $ex) {
                throw $ex;
            }
        }

        return $this->controller;
    }

    /**
     * Get the controller method used for the route.
     *
     * @return string
     */
    protected function getControllerMethod() {
        return $this->parseControllerCallback()[1];
    }

    /**
     * Parse the controller.
     *
     * @return array
     */
    protected function parseControllerCallback() {
        return cstr::parseCallback($this->action['uses']);
    }

    /**
     * Determine if the route matches a given request.
     *
     * @param CHTTP_Request $request
     * @param bool          $includingMethod
     *
     * @return bool
     */
    public function matches(CHTTP_Request $request, $includingMethod = true) {
        $this->compileRoute();

        foreach (self::getValidators() as $validator) {
            if (!$includingMethod && $validator instanceof CRouting_Validator_MethodValidator) {
                continue;
            }

            if (!$validator->matches($this, $request)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compile the route into a Symfony CompiledRoute instance.
     *
     * @return \Symfony\Component\Routing\CompiledRoute
     */
    protected function compileRoute() {
        if (!$this->compiled) {
            $this->compiled = $this->toSymfonyRoute()->compile();
        }
        if (!$this->routeData) {
            $this->routeData = new CRouting_RouteData($this->compiled->getStaticPrefix());
        }

        return $this->compiled;
    }

    /**
     * Bind the route to a given request for execution.
     *
     * @param CHTTP_Request $request
     *
     * @return $this
     */
    public function bind(CHTTP_Request $request) {
        $this->compileRoute();
        if ($this->parameters == null) {
            $this->parameters = (new CRouting_RouteParameterBinder($this))
                ->parameters($request);
        }

        $this->originalParameters = $this->parameters;

        return $this;
    }

    /**
     * Determine if the route has parameters.
     *
     * @return bool
     */
    public function hasParameters() {
        return isset($this->parameters);
    }

    /**
     * Determine a given parameter exists from the route.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasParameter($name) {
        if ($this->hasParameters()) {
            return array_key_exists($name, $this->parameters());
        }

        return false;
    }

    /**
     * Get a given parameter from the route.
     *
     * @param string             $name
     * @param null|string|object $default
     *
     * @return null|string|object
     */
    public function parameter($name, $default = null) {
        return carr::get($this->parameters(), $name, $default);
    }

    /**
     * Get original value of a given parameter from the route.
     *
     * @param string      $name
     * @param null|string $default
     *
     * @return null|string
     */
    public function originalParameter($name, $default = null) {
        return carr::get($this->originalParameters(), $name, $default);
    }

    /**
     * Set a parameter to the given value.
     *
     * @param string             $name
     * @param null|string|object $value
     *
     * @return void
     */
    public function setParameter($name, $value) {
        $this->parameters();

        $this->parameters[$name] = $value;
    }

    /**
     * Unset a parameter on the route if it is set.
     *
     * @param string $name
     *
     * @return void
     */
    public function forgetParameter($name) {
        $this->parameters();

        unset($this->parameters[$name]);
    }

    /**
     * Get the key / value list of parameters for the route.
     *
     * @throws \LogicException
     *
     * @return array
     */
    public function parameters() {
        if (isset($this->parameters)) {
            return $this->parameters;
        }

        throw new LogicException('Route is not bound.');
    }

    /**
     * Get the key / value list of original parameters for the route.
     *
     * @throws \LogicException
     *
     * @return array
     */
    public function originalParameters() {
        if (isset($this->originalParameters)) {
            return $this->originalParameters;
        }

        throw new LogicException('Route is not bound.');
    }

    /**
     * Get the key / value list of parameters without null values.
     *
     * @return array
     */
    public function parametersWithoutNulls() {
        return array_filter($this->parameters(), function ($p) {
            return !is_null($p);
        });
    }

    /**
     * Get all of the parameter names for the route.
     *
     * @return array
     */
    public function parameterNames() {
        if (isset($this->parameterNames)) {
            return $this->parameterNames;
        }

        return $this->parameterNames = $this->compileParameterNames();
    }

    /**
     * Get the parameter names for the route.
     *
     * @return array
     */
    protected function compileParameterNames() {
        preg_match_all('/\{(.*?)\}/', $this->getDomain() . $this->uri, $matches);

        return array_map(function ($m) {
            return trim($m, '?');
        }, $matches[1]);
    }

    /**
     * Get the parameters that are listed in the route / controller signature.
     *
     * @param null|string $subClass
     *
     * @return array
     */
    public function signatureParameters($subClass = null) {
        return CRouting_RouteSignatureParameters::fromAction($this->action, $subClass);
    }

    /**
     * Get the binding field for the given parameter.
     *
     * @param string|int $parameter
     *
     * @return null|string
     */
    public function bindingFieldFor($parameter) {
        $fields = is_int($parameter) ? array_values($this->bindingFields) : $this->bindingFields;

        return carr::get($fields, $parameter);
    }

    /**
     * Get the binding fields for the route.
     *
     * @return array
     */
    public function bindingFields() {
        return $this->bindingFields ? $this->bindingFields : [];
    }

    /**
     * Set the binding fields for the route.
     *
     * @param array $bindingFields
     *
     * @return $this
     */
    public function setBindingFields(array $bindingFields) {
        $this->bindingFields = $bindingFields;

        return $this;
    }

    /**
     * Get the parent parameter of the given parameter.
     *
     * @param string $parameter
     *
     * @return string
     */
    public function parentOfParameter($parameter) {
        $key = array_search($parameter, array_keys($this->parameters));

        if ($key === 0) {
            return;
        }

        return array_values($this->parameters)[$key - 1];
    }

    /**
     * Set a default value for the route.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function defaults($key, $value) {
        $this->defaults[$key] = $value;

        return $this;
    }

    /**
     * Set the default values for the route.
     *
     * @param array $defaults
     *
     * @return $this
     */
    public function setDefaults(array $defaults) {
        $this->defaults = $defaults;

        return $this;
    }

    /**
     * Set a regular expression requirement on the route.
     *
     * @param array|string $name
     * @param null|string  $expression
     *
     * @return $this
     */
    public function where($name, $expression = null) {
        foreach ($this->parseWhere($name, $expression) as $name => $expression) {
            $this->wheres[$name] = $expression;
        }

        return $this;
    }

    /**
     * Parse arguments to the where method into an array.
     *
     * @param array|string $name
     * @param string       $expression
     *
     * @return array
     */
    protected function parseWhere($name, $expression) {
        return is_array($name) ? $name : [$name => $expression];
    }

    /**
     * Set a list of regular expression requirements on the route.
     *
     * @param array $wheres
     *
     * @return $this
     */
    public function setWheres(array $wheres) {
        foreach ($wheres as $name => $expression) {
            $this->where($name, $expression);
        }

        return $this;
    }

    /**
     * Mark this route as a fallback route.
     *
     * @return $this
     */
    public function fallback() {
        $this->isFallback = true;

        return $this;
    }

    /**
     * Set the fallback value.
     *
     * @param bool $isFallback
     *
     * @return $this
     */
    public function setFallback($isFallback) {
        $this->isFallback = $isFallback;

        return $this;
    }

    /**
     * Get the HTTP verbs the route responds to.
     *
     * @return array
     */
    public function methods() {
        return $this->methods;
    }

    /**
     * Determine if the route only responds to HTTP requests.
     *
     * @return bool
     */
    public function httpOnly() {
        return in_array('http', $this->action, true);
    }

    /**
     * Determine if the route only responds to HTTPS requests.
     *
     * @return bool
     */
    public function httpsOnly() {
        return $this->secure();
    }

    /**
     * Determine if the route only responds to HTTPS requests.
     *
     * @return bool
     */
    public function secure() {
        return in_array('https', $this->action, true);
    }

    /**
     * Get or set the domain for the route.
     *
     * @param null|string $domain
     *
     * @return null|$this|string
     */
    public function domain($domain = null) {
        if (is_null($domain)) {
            return $this->getDomain();
        }

        $parsed = CRouting_RouteUri::parse($domain);

        $this->action['domain'] = $parsed->uri;

        $this->bindingFields = array_merge(
            $this->bindingFields,
            $parsed->bindingFields
        );

        return $this;
    }

    /**
     * Get the domain defined for the route.
     *
     * @return null|string
     */
    public function getDomain() {
        return isset($this->action['domain']) ? str_replace(['http://', 'https://'], '', $this->action['domain']) : null;
    }

    /**
     * Get the prefix of the route instance.
     *
     * @return null|string
     */
    public function getPrefix() {
        return carr::get($this->action, 'prefix');
    }

    /**
     * Add a prefix to the route URI.
     *
     * @param string $prefix
     *
     * @return $this
     */
    public function prefix($prefix) {
        $this->updatePrefixOnAction($prefix);

        $uri = rtrim((string) $prefix, '/') . '/' . ltrim($this->uri, '/');

        return $this->setUri($uri !== '/' ? trim($uri, '/') : $uri);
    }

    /**
     * Update the "prefix" attribute on the action array.
     *
     * @param string $prefix
     *
     * @return void
     */
    protected function updatePrefixOnAction($prefix) {
        if (!empty($newPrefix = trim(rtrim((string) $prefix, '/') . '/' . ltrim(carr::get($this->action, 'prefix', ''), '/'), '/'))) {
            $this->action['prefix'] = $newPrefix;
        }
    }

    /**
     * Get the URI associated with the route.
     *
     * @return string
     */
    public function uri() {
        return $this->uri;
    }

    /**
     * Set the URI that the route responds to.
     *
     * @param string $uri
     *
     * @return $this
     */
    public function setUri($uri) {
        $this->uri = $this->parseUri($uri);

        return $this;
    }

    /**
     * Parse the route URI and normalize / store any implicit binding fields.
     *
     * @param string $uri
     *
     * @return string
     */
    protected function parseUri($uri) {
        $this->bindingFields = [];

        return c::tap(CRouting_RouteUri::parse($uri), function ($uri) {
            $this->bindingFields = $uri->bindingFields;
        })->uri;
    }

    /**
     * Get the name of the route instance.
     *
     * @return null|string
     */
    public function getName() {
        return carr::get($this->action, 'as');
    }

    /**
     * Add or change the route name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function name($name) {
        $this->action['as'] = isset($this->action['as']) ? $this->action['as'] . $name : $name;

        return $this;
    }

    /**
     * Determine whether the route's name matches the given patterns.
     *
     * @param mixed ...$patterns
     *
     * @return bool
     */
    public function named(...$patterns) {
        if (is_null($routeName = $this->getName())) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (cstr::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set the handler for the route.
     *
     * @param \Closure|array|string $action
     *
     * @return $this
     */
    public function uses($action) {
        if (is_array($action)) {
            $action = $action[0] . '@' . $action[1];
        }

        $action = is_string($action) ? $this->addGroupNamespaceToStringUses($action) : $action;

        return $this->setAction(array_merge($this->action, $this->parseAction([
            'uses' => $action,
            'controller' => $action,
        ])));
    }

    /**
     * Parse a string based action for the "uses" fluent method.
     *
     * @param string $action
     *
     * @return string
     */
    protected function addGroupNamespaceToStringUses($action) {
        $groupStack = carr::last($this->router->getGroupStack());

        if (isset($groupStack['namespace']) && strpos($action, '\\') !== 0) {
            return $groupStack['namespace'] . '\\' . $action;
        }

        return $action;
    }

    /**
     * Get the action name for the route.
     *
     * @return string
     */
    public function getActionName() {
        return carr::get($this->action, 'controller', 'Closure');
    }

    /**
     * Get the method name of the route action.
     *
     * @return string
     */
    public function getActionMethod() {
        return carr::last(explode('@', $this->getActionName()));
    }

    /**
     * Get the action array or one of its properties for the route.
     *
     * @param null|string $key
     *
     * @return mixed
     */
    public function getAction($key = null) {
        return carr::get($this->action, $key);
    }

    /**
     * Set the action array for the route.
     *
     * @param array $action
     *
     * @return $this
     */
    public function setAction(array $action) {
        $this->action = $action;

        if (isset($this->action['domain'])) {
            $this->domain($this->action['domain']);
        }

        return $this;
    }

    /**
     * Get all middleware, including the ones from the controller.
     *
     * @return array
     */
    public function gatherMiddleware() {
        if (!is_null($this->computedMiddleware)) {
            return $this->computedMiddleware;
        }

        $this->computedMiddleware = [];

        return $this->computedMiddleware = CRouting_Router::uniqueMiddleware(array_merge(
            $this->middleware(),
            $this->controllerMiddleware()
        ));
    }

    /**
     * Get or set the middlewares attached to the route.
     *
     * @param null|array|string $middleware
     *
     * @return $this|array
     */
    public function middleware($middleware = null) {
        if (is_null($middleware)) {
            return (array) (carr::get($this->action, 'middleware', []));
        }

        if (is_string($middleware)) {
            $middleware = func_get_args();
        }

        $this->action['middleware'] = array_merge(
            (array) (carr::get($this->action, 'middleware', [])),
            $middleware
        );

        return $this;
    }

    /**
     * Get the middleware for the route's controller.
     *
     * @return array
     */
    public function controllerMiddleware() {
        if (!$this->isControllerAction()) {
            return [];
        }

        return $this->controllerDispatcher()->getMiddleware(
            $this->getController(),
            $this->getControllerMethod()
        );
    }

    /**
     * Specify middleware that should be removed from the given route.
     *
     * @param array|string $middleware
     *
     * @return $this|array
     */
    public function withoutMiddleware($middleware) {
        $this->action['excluded_middleware'] = array_merge(
            (array) (carr::get($this->action, 'excluded_middleware', [])),
            carr::wrap($middleware)
        );

        return $this;
    }

    /**
     * Get the middleware should be removed from the route.
     *
     * @return array
     */
    public function excludedMiddleware() {
        return (array) (carr::get($this->action, 'excluded_middleware', []));
    }

    /**
     * Specify that the route should not allow concurrent requests from the same session.
     *
     * @param null|int $lockSeconds
     * @param null|int $waitSeconds
     *
     * @return $this
     */
    public function block($lockSeconds = 10, $waitSeconds = 10) {
        $this->lockSeconds = $lockSeconds;
        $this->waitSeconds = $waitSeconds;

        return $this;
    }

    /**
     * Specify that the route should allow concurrent requests from the same session.
     *
     * @return $this
     */
    public function withoutBlocking() {
        return $this->block(null, null);
    }

    /**
     * Get the maximum number of seconds the route's session lock should be held for.
     *
     * @return null|int
     */
    public function locksFor() {
        return $this->lockSeconds;
    }

    /**
     * Get the maximum number of seconds to wait while attempting to acquire a session lock.
     *
     * @return null|int
     */
    public function waitsFor() {
        return $this->waitSeconds;
    }

    /**
     * Get the dispatcher for the route's controller.
     *
     * @return CController_ControllerDispatcher
     */
    public function controllerDispatcher() {
        /*
          if ($this->container->bound(ControllerDispatcherContract::class)) {
          return $this->container->make(ControllerDispatcherContract::class);
          }
         */

        return new CController_ControllerDispatcher($this->container);
    }

    /**
     * Get the route validators for the instance.
     *
     * @return array
     */
    public static function getValidators() {
        if (isset(static::$validators)) {
            return static::$validators;
        }

        // To match the route, we will use a chain of responsibility pattern with the
        // validator implementations. We will spin through each one making sure it
        // passes and then we will know if the route as a whole matches request.
        return static::$validators = [
            new CRouting_Validator_UriValidator(), new CRouting_Validator_MethodValidator(),
            new CRouting_Validator_SchemeValidator(), new CRouting_Validator_HostValidator(),
        ];
    }

    /**
     * Convert the route to a Symfony route.
     *
     * @return \Symfony\Component\Routing\Route
     */
    public function toSymfonyRoute() {
        return new SymfonyRoute(
            preg_replace('/\{(\w+?)\?\}/', '{$1}', $this->uri()),
            $this->getOptionalParameterNames(),
            $this->wheres,
            ['utf8' => true, 'action' => $this->action],
            $this->getDomain() ?: '',
            [],
            $this->methods
        );
    }

    /**
     * Get the optional parameter names for the route.
     *
     * @return array
     */
    public function getOptionalParameterNames() {
        preg_match_all('/\{(\w+?)\?\}/', $this->uri(), $matches);

        return isset($matches[1]) ? array_fill_keys($matches[1], null) : [];
    }

    /**
     * Get the compiled version of the route.
     *
     * @return \Symfony\Component\Routing\CompiledRoute
     */
    public function getCompiled() {
        return $this->compiled;
    }

    /**
     * Set the router instance on the route.
     *
     * @param CRouting_Router $router
     *
     * @return $this
     */
    public function setRouter(CRouting_Router $router) {
        $this->router = $router;

        return $this;
    }

    /**
     * Set the container instance on the route.
     *
     * @param CContainer_Container $container
     *
     * @return $this
     */
    public function setContainer(CContainer_Container $container) {
        $this->container = $container;

        return $this;
    }

    /**
     * Prepare the route instance for serialization.
     *
     * @throws \LogicException
     *
     * @return void
     */
    public function prepareForSerialization() {
        if ($this->action['uses'] instanceof Closure) {
            $this->action['uses'] = serialize(new CFunction_SerializableClosure($this->action['uses']));

            // throw new LogicException("Unable to prepare route [{$this->uri}] for serialization. Uses Closure.");
        }

        $this->compileRoute();

        unset($this->router, $this->container);
    }

    /**
     * Dynamically access route parameters.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get($key) {
        return $this->parameter($key);
    }

    public function routedUri() {
        return c::optional($this->routeData)->getRoutedUri();
    }

    public function setRouteData(CRouting_RouteData $routeData) {
        $this->routeData = $routeData;

        return $this;
    }

    /**
     * @return CRouting_RouteData
     */
    public function getRouteData() {
        return $this->routeData;
    }
}
