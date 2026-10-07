<?php

/**
 * Server MCP tanpa sesi: setiap request berdiri sendiri, jadi tidak ada antrean atau kunci per sesi. Dibangun per request
 * dengan tool yang boleh dipanggil pemanggil; tool yang tidak didaftarkan tidak terlihat dan tidak dapat dipanggil.
 */
class CAI_MCP_Server {
    const ERROR_PARSE = -32700;

    const ERROR_INVALID_REQUEST = -32600;

    const ERROR_METHOD_NOT_FOUND = -32601;

    const ERROR_INVALID_PARAMS = -32602;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $version;

    /**
     * @var null|string
     */
    protected $title;

    /**
     * @var null|string
     */
    protected $instructions;

    /**
     * @var array
     */
    protected $tools = [];

    /**
     * @var null|array
     */
    protected $allowedHosts;

    /**
     * @var null|callable
     */
    protected $errorReporter;

    /**
     * @param string $name
     * @param string $version
     */
    public function __construct($name, $version = '1.0.0') {
        $this->name = (string) $name;
        $this->version = (string) $version;
    }

    /**
     * @param string $title
     *
     * @return $this
     */
    public function setTitle($title) {
        $this->title = (string) $title;

        return $this;
    }

    /**
     * @param string $instructions
     *
     * @return $this
     */
    public function setInstructions($instructions) {
        $this->instructions = (string) $instructions;

        return $this;
    }

    /**
     * Host yang boleh muncul di header Origin; bawaannya hanya host request itu sendiri.
     *
     * @param array $hosts
     *
     * @return $this
     */
    public function setAllowedHosts(array $hosts) {
        $this->allowedHosts = array_map('strtolower', $hosts);

        return $this;
    }

    /**
     * Dipanggil dengan Throwable yang lolos dari handler tool; pesannya tidak dikirim ke klien.
     *
     * @param callable $reporter
     *
     * @return $this
     */
    public function setErrorReporter(callable $reporter) {
        $this->errorReporter = $reporter;

        return $this;
    }

    /**
     * @param string $name
     * @param array  $definition title, description, inputSchema (atau input), annotations, handler
     *
     * @throws InvalidArgumentException
     *
     * @return $this
     */
    public function addTool($name, array $definition) {
        if (!preg_match('/^[A-Za-z0-9_.-]{1,128}$/', (string) $name)) {
            throw new InvalidArgumentException('Nama tool MCP tidak valid: ' . $name);
        }
        if (!isset($definition['handler']) || !is_callable($definition['handler'])) {
            throw new InvalidArgumentException('Tool MCP butuh handler yang dapat dipanggil: ' . $name);
        }
        $schema = isset($definition['inputSchema']) ? $definition['inputSchema'] : (isset($definition['input']) ? $definition['input'] : []);
        $schema = is_array($schema) ? $schema : [];
        $schema['type'] = 'object';
        $definition['inputSchema'] = $schema;
        $this->tools[(string) $name] = $definition;

        return $this;
    }

    /**
     * @return array
     */
    public function toolNames() {
        return array_keys($this->tools);
    }

    /**
     * @param CHTTP_Request $request
     *
     * @return CHTTP_JsonResponse|CHTTP_Response
     */
    public function handle($request) {
        $method = strtoupper($request->getMethod());
        if ($method !== 'POST') {
            return c::response()->json(static::errorEnvelope(null, self::ERROR_INVALID_REQUEST, 'Gunakan POST.'), 405, ['Allow' => 'POST']);
        }
        if (!$this->originAllowed($request)) {
            return c::response()->json(static::errorEnvelope(null, self::ERROR_INVALID_REQUEST, 'Origin tidak diizinkan.'), 403);
        }

        $decoded = json_decode((string) $request->getContent(), true);
        if (!is_array($decoded) || $decoded === []) {
            return c::response()->json(static::errorEnvelope(null, self::ERROR_PARSE, 'Body bukan JSON yang valid.'), 400);
        }

        $isBatch = !isset($decoded['jsonrpc']) && !isset($decoded['method']) && array_keys($decoded) === range(0, count($decoded) - 1);
        $messages = $isBatch ? $decoded : [$decoded];
        $responses = [];
        foreach ($messages as $message) {
            $response = $this->handleMessage($message);
            if ($response !== null) {
                $responses[] = $response;
            }
        }

        if (!$responses) {
            return c::response('', 202);
        }

        return c::response()->json($isBatch ? $responses : $responses[0], 200);
    }

    /**
     * @param mixed $message
     *
     * @return null|array null untuk notifikasi dan respons klien
     */
    public function handleMessage($message) {
        if (!is_array($message) || !isset($message['jsonrpc']) || $message['jsonrpc'] !== '2.0') {
            return static::errorEnvelope(null, self::ERROR_INVALID_REQUEST, 'Pesan JSON-RPC 2.0 tidak valid.');
        }
        $hasId = array_key_exists('id', $message);
        $id = $hasId ? $message['id'] : null;
        if (!isset($message['method'])) {
            //respons dari klien (mis. hasil ping server): tidak ada yang perlu dijawab
            return null;
        }
        if (!is_string($message['method']) || ($hasId && !is_int($id) && !is_string($id))) {
            return static::errorEnvelope(null, self::ERROR_INVALID_REQUEST, 'Pesan JSON-RPC 2.0 tidak valid.');
        }
        if (!$hasId) {
            return null;
        }

        $params = isset($message['params']) && is_array($message['params']) ? $message['params'] : [];

        switch ($message['method']) {
            case 'initialize':
                return static::resultEnvelope($id, $this->initializeResult($params));
            case 'ping':
                return static::resultEnvelope($id, new stdClass());
            case 'tools/list':
                return static::resultEnvelope($id, ['tools' => $this->toolList()]);
            case 'tools/call':
                return $this->callTool($id, $params);
        }

        return static::errorEnvelope($id, self::ERROR_METHOD_NOT_FOUND, 'Method tidak dikenal: ' . $message['method']);
    }

    /**
     * @param array $params
     *
     * @return array
     */
    protected function initializeResult(array $params) {
        $requested = isset($params['protocolVersion']) ? $params['protocolVersion'] : null;
        $version = in_array($requested, CAI_MCP::PROTOCOL_VERSIONS, true) ? $requested : CAI_MCP::PROTOCOL_VERSIONS[0];
        $serverInfo = ['name' => $this->name, 'version' => $this->version];
        if ($this->title !== null) {
            $serverInfo['title'] = $this->title;
        }
        $result = [
            'protocolVersion' => $version,
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => $serverInfo,
        ];
        if ($this->instructions !== null) {
            $result['instructions'] = $this->instructions;
        }

        return $result;
    }

    /**
     * @return array
     */
    protected function toolList() {
        $list = [];
        foreach ($this->tools as $name => $definition) {
            $schema = $definition['inputSchema'];
            if (empty($schema['properties'])) {
                $schema['properties'] = new stdClass();
            }
            $tool = ['name' => $name];
            if (isset($definition['title'])) {
                $tool['title'] = (string) $definition['title'];
            }
            $tool['description'] = isset($definition['description']) ? (string) $definition['description'] : '';
            $tool['inputSchema'] = $schema;
            if (!empty($definition['annotations']) && is_array($definition['annotations'])) {
                $tool['annotations'] = $definition['annotations'];
            }
            $list[] = $tool;
        }

        return $list;
    }

    /**
     * @param int|string $id
     * @param array      $params
     *
     * @return array
     */
    protected function callTool($id, array $params) {
        $name = isset($params['name']) && is_string($params['name']) ? $params['name'] : '';
        if (!isset($this->tools[$name])) {
            return static::errorEnvelope($id, self::ERROR_INVALID_PARAMS, 'Tool tidak dikenal: ' . $name);
        }
        $arguments = isset($params['arguments']) ? $params['arguments'] : [];
        if (!is_array($arguments)) {
            return static::errorEnvelope($id, self::ERROR_INVALID_PARAMS, 'arguments harus berupa objek.');
        }

        $definition = $this->tools[$name];
        $errors = CAI_MCP_Schema::validate($definition['inputSchema'], $arguments);
        if ($errors) {
            return static::resultEnvelope($id, CAI_MCP_Result::error('Argumen tidak valid: ' . implode('; ', $errors))->toArray());
        }

        try {
            $output = call_user_func($definition['handler'], $arguments);
        } catch (CAI_MCP_Exception_ToolException $e) {
            return static::resultEnvelope($id, CAI_MCP_Result::error($e->getMessage())->toArray());
        } catch (Throwable $e) {
            if ($this->errorReporter !== null) {
                call_user_func($this->errorReporter, $e, $name);
            }

            return static::resultEnvelope($id, CAI_MCP_Result::error('Tool gagal dijalankan.')->toArray());
        }

        if (!$output instanceof CAI_MCP_Result) {
            $output = is_string($output) ? CAI_MCP_Result::text($output) : CAI_MCP_Result::json($output);
        }

        return static::resultEnvelope($id, $output->toArray());
    }

    /**
     * @param CHTTP_Request $request
     *
     * @return bool
     */
    protected function originAllowed($request) {
        $origin = $request->headers->get('Origin');
        if ($origin === null || $origin === '') {
            return true;
        }
        $host = parse_url((string) $origin, PHP_URL_HOST);
        if ($host === null || $host === false) {
            return false;
        }
        $allowed = $this->allowedHosts !== null ? $this->allowedHosts : [strtolower($request->getHost())];

        return in_array(strtolower($host), $allowed, true);
    }

    /**
     * @param int|string $id
     * @param mixed      $result
     *
     * @return array
     */
    protected static function resultEnvelope($id, $result) {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /**
     * @param null|int|string $id
     * @param int             $code
     * @param string          $message
     *
     * @return array
     */
    protected static function errorEnvelope($id, $code, $message) {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
