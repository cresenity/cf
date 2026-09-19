<?php
use PHPUnit\Framework\TestCase;

class UjiWebhook_Signer implements CWebhook_Server_Contract_SignerInterface {
    public function calculateSignature($webhookUrl, array $payload, $secret) {
        return 'uji-' . md5($webhookUrl . json_encode($payload) . $secret);
    }

    public function signatureHeaderName() {
        return 'X-Uji-Signature';
    }
}

class UjiWebhook_Backoff implements CWebhook_Server_Contract_BackoffStrategyInterface {
    public function waitInSecondsAfterAttempt($attempt) {
        return 5 * $attempt;
    }
}

class UjiWebhook_RejectProfile implements CWebhook_Client_Contract_WebhookProfileInterface {
    public function shouldProcess(CHTTP_Request $request) {
        return false;
    }
}

class UjiWebhook_ProcessJob extends CWebhook_Client_TaskQueue_AbstractProcessWebhookTask {
    public function handle() {
    }
}

class UjiWebhook_WebhookModel {
    /** @var array */
    public static $stored = [];

    public static function storeWebhook($config, $request) {
        static::$stored[] = ['name' => $config->name, 'body' => $request->getContent()];

        return new static();
    }

    public function clearException() {
    }

    public function saveException($e) {
    }
}

/**
 * CWebhook: sisi server (WebhookCall builder, penandatanganan HMAC, backoff, validasi kelas) dan
 * sisi client (Config, validasi tanda tangan, profil, processor tanpa penyimpanan nyata).
 */
class WebhookTest extends TestCase {
    /**
     * @return CWebhook_Server_TaskQueue_CallWebhookTask
     */
    protected function job(CWebhook_Server_WebhookCall $call) {
        $property = new ReflectionProperty($call, 'callWebhookJob');
        $property->setAccessible(true);

        return $property->getValue($call);
    }

    /**
     * @param CWebhook_Server_WebhookCall $call
     *
     * @return CWebhook_Server_TaskQueue_CallWebhookTask
     */
    protected function prepared(CWebhook_Server_WebhookCall $call) {
        $method = new ReflectionMethod($call, 'prepareForDispatch');
        $method->setAccessible(true);
        $method->invoke($call);

        return $this->job($call);
    }

    public function testCreateAppliesTheDefaultServerConfig() {
        $call = CWebhook::server()->create();
        $this->assertInstanceOf(CWebhook_Server_WebhookCall::class, $call);
        $job = $this->job($call);
        $this->assertSame('post', $job->httpVerb);
        $this->assertSame(3, $job->tries);
        $this->assertSame(3, $job->requestTimeout);
        $this->assertSame(CWebhook_Server_BackoffStrategy_ExponentialBackoffStrategy::class, $job->backoffStrategyClass);
        $this->assertTrue($job->verifySsl);
        $this->assertFalse($job->throwExceptionOnFailure);
        $this->assertSame([], $job->headers, 'header baru dipasang ke job saat dispatch');
        $job = $this->prepared($call->url('https://hook.uji.test')->useSecret('s'));
        $this->assertSame(['Content-Type', 'Signature'], array_keys($job->headers));
        $this->assertNotEmpty($call->getUuid());
        $this->assertSame($call->getUuid(), $job->uuid);
    }

    public function testSignedHeadersUseHmacSha256OfTheJsonPayload() {
        $call = CWebhook_Server_WebhookCall::create()->url('https://hook.uji.test/in')->payload(['a' => 1, 'b' => [2, 3]])->useSecret('rahasia');
        $job = $this->prepared($call);
        $expected = hash_hmac('sha256', json_encode(['a' => 1, 'b' => [2, 3]]), 'rahasia');
        $this->assertSame($expected, $job->headers['Signature']);
        $this->assertSame('application/json', $job->headers['Content-Type']);
        $this->assertSame(['a' => 1, 'b' => [2, 3]], $job->payload);
        $this->assertSame('https://hook.uji.test/in', $job->webhookUrl);
    }

    public function testCustomSignerHeadersAndMeta() {
        $call = CWebhook_Server_WebhookCall::create()->url('https://hook.uji.test')->payload(['x' => 1])->useSecret('s')
            ->signUsing(UjiWebhook_Signer::class)
            ->withHeaders(['X-App' => 'cf'])
            ->useHttpVerb('put')
            ->maximumTries(5)
            ->timeoutInSeconds(9)
            ->doNotVerifySsl()
            ->throwExceptionOnFailure()
            ->meta(['order_id' => 7])
            ->withTags(['order'])
            ->onQueue('webhooks')
            ->useBackoffStrategy(UjiWebhook_Backoff::class);
        $job = $this->prepared($call);
        $this->assertSame('uji-' . md5('https://hook.uji.test' . json_encode(['x' => 1]) . 's'), $job->headers['X-Uji-Signature']);
        $this->assertSame('cf', $job->headers['X-App']);
        $this->assertSame('application/json', $job->headers['Content-Type'], 'withHeaders() menambah ke header config');
        $this->assertSame('put', $job->httpVerb);
        $this->assertSame(5, $job->tries);
        $this->assertSame(9, $job->requestTimeout);
        $this->assertFalse($job->verifySsl);
        $this->assertTrue($job->throwExceptionOnFailure);
        $this->assertSame(['order_id' => 7], $job->meta);
        $this->assertSame(['order'], $job->tags());
        $this->assertSame('webhooks', $job->queue);
        $this->assertSame(UjiWebhook_Backoff::class, $job->backoffStrategyClass);
    }

    public function testDoNotSignOmitsTheSignatureAndSecret() {
        $call = CWebhook_Server_WebhookCall::create()->url('https://hook.uji.test')->payload(['x' => 1])->doNotSign();
        $job = $this->prepared($call);
        $this->assertArrayNotHasKey('Signature', $job->headers);
    }

    public function testDispatchRequiresUrlAndSecret() {
        try {
            CWebhook_Server_WebhookCall::create()->payload(['x' => 1])->useSecret('s')->dispatch();
            $this->fail('tanpa url harus melempar');
        } catch (CWebhook_Server_Exception_CouldNotCallWebhookException $e) {
            $this->assertStringContainsString('url', strtolower($e->getMessage()));
        }
        $this->expectException(CWebhook_Server_Exception_CouldNotCallWebhookException::class);
        CWebhook_Server_WebhookCall::create()->url('https://hook.uji.test')->dispatch();
    }

    public function testInvalidSignerAndBackoffClassesAreRejected() {
        try {
            CWebhook_Server_WebhookCall::create()->signUsing(stdClass::class);
            $this->fail('signer bukan SignerInterface harus ditolak');
        } catch (CWebhook_Server_Exception_InvalidSignerException $e) {
            $this->assertStringContainsString('stdClass', $e->getMessage());
        }
        $this->expectException(CWebhook_Server_Exception_InvalidBackoffStrategyException::class);
        CWebhook_Server_WebhookCall::create()->useBackoffStrategy(stdClass::class);
    }

    public function testClientFacadeReadsNamedConfigOrThrows() {
        try {
            CWebhook::client()->config('tidak-ada-' . uniqid());
            $this->fail('config tak dikenal harus melempar');
        } catch (CWebhook_Client_Exception_InvalidConfigException $e) {
            $this->assertStringContainsString('Could not find the configuration', $e->getMessage());
        }
    }

    public function testExponentialBackoff() {
        $strategy = new CWebhook_Server_BackoffStrategy_ExponentialBackoffStrategy();
        $this->assertSame(10, $strategy->waitInSecondsAfterAttempt(1));
        $this->assertSame(100, $strategy->waitInSecondsAfterAttempt(2));
        $this->assertSame(10000, $strategy->waitInSecondsAfterAttempt(4));
        $this->assertSame(100000, $strategy->waitInSecondsAfterAttempt(5), 'dibatasi ~28 jam setelah percobaan ke-4');
        $this->assertSame(100000, $strategy->waitInSecondsAfterAttempt(50));
    }

    public function testDispatchSendsTheJobToTheBus() {
        $fake = new CTesting_Fake_Base_BusFake(CQueue::dispatcher());
        $property = new ReflectionProperty(CQueue::class, 'dispatcher');
        $property->setAccessible(true);
        $original = CQueue::dispatcher();
        $property->setValue(null, $fake);
        try {
            CWebhook_Server_WebhookCall::create()->url('https://hook.uji.test')->payload(['x' => 1])->useSecret('s')->onQueue('hooks')->dispatch();
            $fake->assertDispatched(CWebhook_Server_TaskQueue_CallWebhookTask::class, function (CWebhook_Server_TaskQueue_CallWebhookTask $job) {
                return $job->webhookUrl === 'https://hook.uji.test' && $job->queue === 'hooks' && isset($job->headers['Signature']);
            });
        } finally {
            $property->setValue(null, $original);
        }
    }

    /**
     * @return array
     */
    protected function clientProperties(array $overrides = []) {
        return array_merge([
            'name' => 'uji',
            'signing_secret' => 'client-secret',
            'signature_header_name' => 'Signature',
            'signature_validator' => CWebhook_Client_SignatureValidator_DefaultSignatureValidator::class,
            'webhook_profile' => CWebhook_Client_WebhookProfile_ProcessEverythingWebhookProfile::class,
            'webhook_model' => UjiWebhook_WebhookModel::class,
            'process_webhook_job' => UjiWebhook_ProcessJob::class,
        ], $overrides);
    }

    public function testClientConfigResolvesItsCollaborators() {
        $config = new CWebhook_Client_Config($this->clientProperties(['store_headers' => ['X-Id']]));
        $this->assertSame('uji', $config->name);
        $this->assertSame('client-secret', $config->signingSecret);
        $this->assertInstanceOf(CWebhook_Client_SignatureValidator_DefaultSignatureValidator::class, $config->signatureValidator);
        $this->assertInstanceOf(CWebhook_Client_WebhookProfile_ProcessEverythingWebhookProfile::class, $config->webhookProfile);
        $this->assertInstanceOf(CWebhook_Client_WebhookResponse_DefaultWebhookResponse::class, $config->webhookResponse, 'response default');
        $this->assertSame(UjiWebhook_WebhookModel::class, $config->webhookModel);
        $this->assertSame(UjiWebhook_ProcessJob::class, $config->processWebhookJobClass);
        $this->assertSame(['X-Id'], $config->storeHeaders);
    }

    public function testClientConfigRejectsInvalidClasses() {
        $cases = [
            ['signature_validator' => stdClass::class],
            ['webhook_profile' => stdClass::class],
            ['webhook_response' => stdClass::class],
            ['process_webhook_job' => stdClass::class],
        ];
        foreach ($cases as $override) {
            try {
                new CWebhook_Client_Config($this->clientProperties($override));
                $this->fail(key($override) . ' tidak valid harus ditolak');
            } catch (CWebhook_Client_Exception_InvalidConfigException $e) {
                $this->assertStringContainsString('stdClass', $e->getMessage());
            }
        }
    }

    /**
     * @param string $body
     * @param array  $headers
     *
     * @return CHTTP_Request
     */
    protected function incoming($body, array $headers = []) {
        $server = ['CONTENT_TYPE' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return CHTTP_Request::create('/webhook', 'POST', [], [], [], $server, $body);
    }

    public function testDefaultSignatureValidatorMatchesHmacOfTheRawBody() {
        $config = new CWebhook_Client_Config($this->clientProperties());
        $validator = $config->signatureValidator;
        $body = '{"event":"paid","id":9}';
        $good = hash_hmac('sha256', $body, 'client-secret');
        $this->assertTrue($validator->isValid($this->incoming($body, ['Signature' => $good]), $config));
        $this->assertFalse($validator->isValid($this->incoming($body, ['Signature' => 'salah']), $config));
        $this->assertFalse($validator->isValid($this->incoming($body), $config), 'tanpa header → tidak valid');
        $this->assertFalse($validator->isValid($this->incoming($body . ' ', ['Signature' => $good]), $config), 'body berubah satu karakter');
    }

    public function testValidatorWithoutSecretThrows() {
        $config = new CWebhook_Client_Config($this->clientProperties(['signing_secret' => '']));
        $this->expectException(CWebhook_Client_Exception_InvalidConfigException::class);
        $config->signatureValidator->isValid($this->incoming('{}', ['Signature' => 'x']), $config);
    }

    public function testProcessorRejectsAnInvalidSignatureAndFiresAnEvent() {
        $seen = [];
        CEvent::dispatcher()->listen(CWebhook_Client_Event_InvalidWebhookSignatureEvent::class, function ($event) use (&$seen) {
            $seen[] = get_class($event);
        });
        $config = new CWebhook_Client_Config($this->clientProperties());
        $processor = new CWebhook_Client_WebhookProcessor($this->incoming('{}', ['Signature' => 'palsu']), $config);
        try {
            $processor->process();
            $this->fail('tanda tangan palsu harus ditolak');
        } catch (CWebhook_Client_Exception_InvalidWebhookSignatureException $e) {
            $this->assertSame([CWebhook_Client_Event_InvalidWebhookSignatureEvent::class], $seen);
        }
    }

    public function testProcessorSkipsStorageWhenTheProfileRejects() {
        UjiWebhook_WebhookModel::$stored = [];
        $config = new CWebhook_Client_Config($this->clientProperties(['webhook_profile' => UjiWebhook_RejectProfile::class]));
        $body = '{"event":"ignored"}';
        $response = (new CWebhook_Client_WebhookProcessor($this->incoming($body, ['Signature' => hash_hmac('sha256', $body, 'client-secret')]), $config))->process();
        $this->assertInstanceOf(CHTTP_JsonResponse::class, $response);
        $this->assertSame(['message' => 'ok'], json_decode($response->getContent(), true));
        $this->assertSame([], UjiWebhook_WebhookModel::$stored, 'profil menolak → tidak disimpan, tetap 200');
    }

    public function testProcessorStoresAndDispatchesWhenValid() {
        UjiWebhook_WebhookModel::$stored = [];
        $fake = new CTesting_Fake_Base_BusFake(CQueue::dispatcher());
        $property = new ReflectionProperty(CQueue::class, 'dispatcher');
        $property->setAccessible(true);
        $original = CQueue::dispatcher();
        $property->setValue(null, $fake);
        try {
            $config = new CWebhook_Client_Config($this->clientProperties());
            $body = '{"event":"paid"}';
            $response = (new CWebhook_Client_WebhookProcessor($this->incoming($body, ['Signature' => hash_hmac('sha256', $body, 'client-secret')]), $config))->process();
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame([['name' => 'uji', 'body' => $body]], UjiWebhook_WebhookModel::$stored);
            $fake->assertDispatched(UjiWebhook_ProcessJob::class);
        } finally {
            $property->setValue(null, $original);
        }
    }
}
