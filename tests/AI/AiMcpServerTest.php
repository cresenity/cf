<?php

use PHPUnit\Framework\TestCase;

/**
 * CAI_MCP_Server: protokol JSON-RPC MCP (tool saja, tanpa sesi).
 */
class AiMcpServerTest extends TestCase {
    /**
     * @return CAI_MCP_Server
     */
    private function server() {
        return CAI_MCP::server('uji', '1.2.3')
            ->setTitle('Server Uji')
            ->setInstructions('Petunjuk uji')
            ->addTool('echo', [
                'title' => 'Gema',
                'description' => 'Mengembalikan teks',
                'annotations' => ['readOnlyHint' => true],
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => ['type' => 'string', 'minLength' => 1],
                        'times' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 3],
                        'mode' => ['type' => 'string', 'enum' => ['a', 'b']],
                    ],
                    'required' => ['text'],
                ],
                'handler' => function (array $args) {
                    return str_repeat($args['text'], isset($args['times']) ? $args['times'] : 1);
                },
            ])
            ->addTool('noargs', ['description' => 'Tanpa argumen', 'handler' => function () {
                return ['ok' => true];
            }])
            ->addTool('denied', ['handler' => function () {
                throw new CAI_MCP_Exception_ToolException('Tidak boleh');
            }])
            ->addTool('boom', ['handler' => function () {
                throw new RuntimeException('rahasia internal');
            }]);
    }

    /**
     * @param mixed  $body
     * @param string $method
     * @param array  $headers
     *
     * @return CHTTP_Response
     */
    private function post($body, $method = 'POST', array $headers = []) {
        $request = CHTTP_Request::create('https://mcp.example.test/mcp', $method, [], [], [], ['CONTENT_TYPE' => 'application/json'], is_string($body) ? $body : json_encode($body));
        foreach ($headers as $key => $value) {
            $request->headers->set($key, $value);
        }

        return $this->server()->handle($request);
    }

    /**
     * @param mixed $body
     *
     * @return array
     */
    private function rpc($body) {
        $response = $this->post($body);
        $this->assertSame(200, $response->getStatusCode());

        return json_decode($response->getContent(), true);
    }

    public function testInitializeNegotiatesVersionAndDescribesServer() {
        $result = $this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-03-26']])['result'];
        $this->assertSame('2025-03-26', $result['protocolVersion']);
        $this->assertSame(['name' => 'uji', 'version' => '1.2.3', 'title' => 'Server Uji'], $result['serverInfo']);
        $this->assertSame('Petunjuk uji', $result['instructions']);
        $this->assertSame(['tools' => ['listChanged' => false]], $result['capabilities']);

        $unknown = $this->rpc(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'initialize', 'params' => ['protocolVersion' => '1999-01-01']])['result'];
        $this->assertSame(CAI_MCP::PROTOCOL_VERSIONS[0], $unknown['protocolVersion']);
    }

    public function testToolsListShapesSchemaAndAnnotations() {
        $tools = $this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])['result']['tools'];
        $byName = [];
        foreach ($tools as $tool) {
            $byName[$tool['name']] = $tool;
        }
        $this->assertSame(['echo', 'noargs', 'denied', 'boom'], array_keys($byName));
        $this->assertSame('object', $byName['echo']['inputSchema']['type']);
        $this->assertSame(['readOnlyHint' => true], $byName['echo']['annotations']);
        $raw = $this->post(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->getContent();
        $this->assertStringContainsString('"properties":{}', $raw, 'properties kosong harus berupa objek JSON, bukan array');
    }

    public function testToolsCallReturnsTextAndJson() {
        $text = $this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'echo', 'arguments' => ['text' => 'ab', 'times' => 2]]])['result'];
        $this->assertFalse($text['isError']);
        $this->assertSame([['type' => 'text', 'text' => 'abab']], $text['content']);

        $json = $this->rpc(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'noargs']])['result'];
        $this->assertSame(['ok' => true], json_decode($json['content'][0]['text'], true));
    }

    public function testInvalidArgumentsAreToolErrorsNotProtocolErrors() {
        foreach ([[], ['text' => ''], ['text' => 'x', 'times' => 9], ['text' => 'x', 'mode' => 'z'], ['text' => 5]] as $arguments) {
            $response = $this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'echo', 'arguments' => $arguments]]);
            $this->assertArrayNotHasKey('error', $response, json_encode($arguments));
            $this->assertTrue($response['result']['isError'], json_encode($arguments));
            $this->assertStringContainsString('Argumen tidak valid', $response['result']['content'][0]['text']);
        }
    }

    public function testToolExceptionMessageReachesClientButInternalErrorsDoNot() {
        $denied = $this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'denied']])['result'];
        $this->assertTrue($denied['isError']);
        $this->assertSame('Tidak boleh', $denied['content'][0]['text']);

        $reported = [];
        $server = $this->server()->setErrorReporter(function ($e, $name) use (&$reported) {
            $reported[] = [$name, $e->getMessage()];
        });
        $request = CHTTP_Request::create('https://mcp.example.test/mcp', 'POST', [], [], [], [], json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'boom']]));
        $boom = json_decode($server->handle($request)->getContent(), true)['result'];
        $this->assertTrue($boom['isError']);
        $this->assertStringNotContainsString('rahasia', $boom['content'][0]['text']);
        $this->assertSame([['boom', 'rahasia internal']], $reported);
    }

    public function testUnknownToolAndMethodAreProtocolErrors() {
        $tool = $this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'nope']]);
        $this->assertSame(CAI_MCP_Server::ERROR_INVALID_PARAMS, $tool['error']['code']);
        $method = $this->rpc(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'resources/list']);
        $this->assertSame(CAI_MCP_Server::ERROR_METHOD_NOT_FOUND, $method['error']['code']);
        $this->assertSame(2, $method['id']);
    }

    public function testNotificationsAndClientResponsesGet202WithoutBody() {
        foreach ([['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], ['jsonrpc' => '2.0', 'id' => 5, 'result' => []]] as $message) {
            $response = $this->post($message);
            $this->assertSame(202, $response->getStatusCode());
            $this->assertSame('', $response->getContent());
        }
    }

    public function testBatchAnswersOnlyRequests() {
        $responses = $this->rpc([
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'],
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
            ['jsonrpc' => '2.0', 'id' => 'b', 'method' => 'tools/call', 'params' => ['name' => 'noargs']],
        ]);
        $this->assertCount(2, $responses);
        $this->assertSame([1, 'b'], array_column($responses, 'id'));
    }

    public function testPingReturnsEmptyObject() {
        $this->assertStringContainsString('"result":{}', $this->post(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])->getContent());
    }

    public function testMalformedRequestsAreRejected() {
        $this->assertSame(400, $this->post('{bukan json')->getStatusCode());
        $this->assertSame(400, $this->post('[]')->getStatusCode());
        $invalid = json_decode($this->post(['id' => 1, 'method' => 'ping'])->getContent(), true);
        $this->assertSame(CAI_MCP_Server::ERROR_INVALID_REQUEST, $invalid['error']['code']);
        $badId = json_decode($this->post(['jsonrpc' => '2.0', 'id' => ['x'], 'method' => 'ping'])->getContent(), true);
        $this->assertSame(CAI_MCP_Server::ERROR_INVALID_REQUEST, $badId['error']['code']);
    }

    public function testOnlyPostIsAccepted() {
        foreach (['GET', 'DELETE', 'PUT'] as $method) {
            $response = $this->post('', $method);
            $this->assertSame(405, $response->getStatusCode(), $method);
            $this->assertSame('POST', $response->headers->get('Allow'));
        }
    }

    public function testOriginMustMatchHostOrAllowList() {
        $this->assertSame(403, $this->post(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'], 'POST', ['Origin' => 'https://evil.example'])->getStatusCode());
        $this->assertSame(200, $this->post(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'], 'POST', ['Origin' => 'https://mcp.example.test'])->getStatusCode());
        $this->assertSame(200, $this->post(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])->getStatusCode(), 'klien non-browser tanpa Origin');

        $server = $this->server()->setAllowedHosts(['other.example']);
        $request = CHTTP_Request::create('https://mcp.example.test/mcp', 'POST', [], [], [], [], json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']));
        $request->headers->set('Origin', 'https://other.example');
        $this->assertSame(200, $server->handle($request)->getStatusCode());
    }

    public function testToolCallsInBodyListsNamesForSingleAndBatch() {
        $single = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'a']]);
        $this->assertSame(['a'], CAI_MCP::toolCalls($single));
        $batch = json_encode([
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'a']],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'],
            ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'b']],
        ]);
        $this->assertSame(['a', 'b'], CAI_MCP::toolCalls($batch));
        $this->assertSame([], CAI_MCP::toolCalls('bukan json'));
    }

    public function testInvalidToolDefinitionsAreRejected() {
        $server = CAI_MCP::server('x');
        $this->expectException(InvalidArgumentException::class);
        $server->addTool('nama tidak valid', ['handler' => function () {
        }]);
    }

    public function testToolWithoutHandlerIsRejected() {
        $this->expectException(InvalidArgumentException::class);
        CAI_MCP::server('x')->addTool('ok', ['description' => 'tanpa handler']);
    }

    public function testFacadeShortcut() {
        $this->assertInstanceOf(CAI_MCP_Server::class, CAI::mcp('x', '9'));
    }
}
