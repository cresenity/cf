<?php
/**
 * @see https://github.com/shetabit/visitor/tree/master
 */
class CApp_Visitor {
    /**
     * Except.
     *
     * @var array
     */
    protected $except;

    /**
     * Configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * Driver name.
     *
     * @var string
     */
    protected $driver;

    /**
     * Driver instance.
     *
     * @var object
     */
    protected $driverInstance;

    /**
     * Request instance.
     *
     * @var CHTTP_Request
     */
    protected $request;

    /**
     * Visitor (user) instance.
     *
     * @var null|CModel
     */
    protected $visitor;

    /**
     * Visitor constructor.
     *
     * @param array $config
     *
     * @throws \Exception
     */
    public function __construct($config) {
        $this->request = c::request();
        $this->config = $config;
        $this->except = carr::get($config, 'except', []);
        $this->via($this->config['default']);
        $this->setVisitor($this->request->user());
    }

    /**
     * Change the driver on the fly.
     *
     * @param string $driver
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function via($driver) {
        $this->driver = $driver;
        $this->validateDriver();

        return $this;
    }

    /**
     * Retrieve request's data.
     *
     * @return array
     */
    public function request() : array {
        return $this->request->all();
    }

    /**
     * Retrieve user's ip.
     *
     * @return null|string
     */
    public function ip() : ?string {
        return $this->request->ip();
    }

    /**
     * Retrieve request's url.
     *
     * @return string
     */
    public function url() : string {
        return $this->request->fullUrl();
    }

    /**
     * Retrieve request's referer.
     *
     * @return null|string
     */
    public function referer() : ?string {
        return $_SERVER['HTTP_REFERER'] ?? null;
    }

    /**
     * Retrieve request's method.
     *
     * @return string
     */
    public function method() : string {
        return $this->request->getMethod();
    }

    /**
     * Retrieve http headers.
     *
     * @return array
     */
    public function httpHeaders() : array {
        return $this->request->headers->all();
    }

    /**
     * Retrieve agent.
     *
     * @return string
     */
    public function userAgent() : string {
        return $this->request->userAgent() ?? '';
    }

    /**
     * Retrieve device's name.
     *
     * @throws \Exception
     *
     * @return string
     */
    public function device() : string {
        return $this->getDriverInstance()->device();
    }

    /**
     * Retrieve platform's name.
     *
     * @throws \Exception
     *
     * @return string
     */
    public function platform() : string {
        return $this->getDriverInstance()->platform();
    }

    /**
     * Retrieve browser's name.
     *
     * @throws \Exception
     *
     * @return string
     */
    public function browser() : string {
        return $this->getDriverInstance()->browser();
    }

    /**
     * Retrieve languages.
     *
     * @throws \Exception
     *
     * @return array
     */
    public function languages() : array {
        return $this->getDriverInstance()->languages();
    }

    /**
     * Set visitor (user).
     *
     * @param null|CModel $user
     *
     * @return $this
     */
    public function setVisitor(?CModel $user) {
        $this->visitor = $user;

        return $this;
    }

    /**
     * Retrieve visitor (user).
     *
     * @return null|CModel
     */
    public function getVisitor() : ?CModel {
        return $this->visitor;
    }

    /**
     * Create a visit log.
     *
     * @param CModel $model
     *
     * @return mixed
     */
    public function visit(?CModel $model = null) {
        foreach ($this->except as $path) {
            if ($this->request->is($path)) {
                return;
            }
        }

        $data = $this->prepareLog();

        if (null !== $model && method_exists($model, 'visitLogs')) {
            $visit = $model->visitLogs()->create($data);
        } else {
            $visit = CApp_Model_Visit::create($data);
        }

        return $visit;
    }

    /**
     * Retrieve online visitors.
     *
     * @param string $model
     * @param int    $seconds
     *
     * @return mixed
     */
    public function onlineVisitors(string $model, $seconds = 180) {
        return c::container($model)->online()->get();
    }

    /**
     * Determine if given visitor or current one is online.
     *
     * @param null|CModel $visitor
     * @param int         $seconds
     *
     * @return bool
     */
    public function isOnline(?CModel $visitor = null, $seconds = 180) {
        $time = c::now()->subSeconds($seconds);

        $visitor = $visitor ?? $this->getVisitor();

        if (empty($visitor)) {
            return false;
        }

        return CApp_Model_Visit::whereHasMorph('visitor', get_class($visitor), function ($query) use ($visitor, $time) {
            $query->where('visitor_id', $visitor->id);
        })->whereDate('created_at', '>=', $time)->count() > 0;
    }

    /**
     * Prepare log's data.
     *
     * @throws \Exception
     *
     * @return array
     */
    protected function prepareLog() : array {

        return [
            'org_id' => c::app()->base()->orgId(),
            'method' => $this->method(),
            'request' => $this->request(),
            'url' => $this->url(),
            'referer' => $this->referer(),
            'languages' => $this->languages(),
            'useragent' => $this->userAgent(),
            'headers' => $this->httpHeaders(),
            'device' => $this->device(),
            'platform' => $this->platform(),
            'browser' => $this->browser(),
            'ip' => $this->ip(),
            'visitor_id' => $this->getVisitor() ? $this->getVisitor()->id : null,
            'visitor_type' => $this->getVisitor() ? get_class($this->getVisitor()) : null
        ];
    }

    /**
     * Retrieve current driver instance or generate new one.
     *
     * @throws \Exception
     *
     * @return mixed|object
     */
    protected function getDriverInstance() {
        if (!empty($this->driverInstance)) {
            return $this->driverInstance;
        }

        return $this->getFreshDriverInstance();
    }

    /**
     * Get new driver instance.
     *
     * @throws \Exception
     *
     * @return CApp_Visitor_Contract_UserAgentParserInterface
     */
    protected function getFreshDriverInstance() {
        $this->validateDriver();

        $driverClass = $this->config['drivers'][$this->driver];

        return new $driverClass($this->request);
    }

    /**
     * Validate driver.
     *
     * @throws \Exception
     */
    protected function validateDriver() {
        if (empty($this->driver)) {
            throw new CApp_Exception_DriverNotFoundException('Driver not selected or default driver does not exist.');
        }

        $driverClass = $this->config['drivers'][$this->driver];

        if (empty($driverClass) || !class_exists($driverClass)) {
            throw new CApp_Exception_DriverNotFoundException('Driver not found in config file. Try updating the package.');
        }

        $reflect = new \ReflectionClass($driverClass);

        if (!$reflect->implementsInterface(CApp_Visitor_Contract_UserAgentParserInterface::class)) {
            throw new \Exception("Driver must be an instance of Contracts\Driver.");
        }
    }
}
