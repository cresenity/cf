<?php
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Exception\AssertionFailedError;

/**
 * CTesting_TestResponse di atas CHTTP_Response/JsonResponse/RedirectResponse tanpa request nyata.
 */
class TestResponseTest extends TestCase {
    /**
     * @param string $content
     * @param int    $status
     * @param array  $headers
     *
     * @return CTesting_TestResponse
     */
    protected function html($content, $status = 200, array $headers = []) {
        return CTesting_TestResponse::fromBaseResponse(new CHTTP_Response($content, $status, $headers));
    }

    /**
     * @param mixed $data
     * @param int   $status
     *
     * @return CTesting_TestResponse
     */
    protected function json($data, $status = 200) {
        return CTesting_TestResponse::fromBaseResponse(new CHTTP_JsonResponse($data, $status));
    }

    /**
     * @param callable $assertion
     * @param string   $needle
     *
     * @return void
     */
    protected function assertFails(callable $assertion, $needle = '') {
        try {
            $assertion();
        } catch (AssertionFailedError $e) {
            if ($needle !== '') {
                $this->assertStringContainsString($needle, $e->getMessage());
            }

            return;
        }
        $this->fail('asersi seharusnya gagal');
    }

    public function testStatusAssertions() {
        $this->html('ok')->assertOk()->assertSuccessful()->assertStatus(200);
        $this->html('', 201)->assertCreated();
        $this->html('', 204)->assertNoContent();
        $this->html('', 404)->assertNotFound();
        $this->html('', 403)->assertForbidden();
        $this->html('', 401)->assertUnauthorized();
        $this->assertFails(function () {
            $this->html('', 500)->assertOk();
        }, '500');
        $this->assertFails(function () {
            $this->html('', 200)->assertStatus(201);
        });
    }

    public function testRedirectAssertions() {
        $response = CTesting_TestResponse::fromBaseResponse(new CHTTP_RedirectResponse('/masuk', 302));
        $response->assertRedirect()->assertRedirect('/masuk')->assertLocation('/masuk')->assertStatus(302);
        $this->assertFails(function () {
            $this->html('bukan redirect')->assertRedirect();
        }, 'not a redirect');
    }

    public function testHeaderAssertions() {
        $response = $this->html('x', 200, ['X-Uji' => 'nilai']);
        $response->assertHeader('X-Uji')->assertHeader('x-uji', 'nilai')->assertHeaderMissing('X-Lain');
        $this->assertFails(function () use ($response) {
            $response->assertHeader('X-Uji', 'lain');
        }, 'does not match');
        $this->assertFails(function () use ($response) {
            $response->assertHeaderMissing('X-Uji');
        });
    }

    public function testSeeAssertionsEscapeByDefault() {
        $response = $this->html('<p>Halo &amp; selamat <b>datang</b> Hery</p>');
        $response->assertSee('Halo & selamat')->assertSee('<b>datang</b>', false)->assertSeeText('Halo & selamat datang Hery');
        $response->assertSeeInOrder(['Halo', 'datang', 'Hery'])->assertSeeTextInOrder(['selamat', 'Hery']);
        $response->assertDontSee('<b>datang</b>', true)->assertDontSeeText('Selamat Tinggal');
        $this->assertFails(function () use ($response) {
            $response->assertSeeInOrder(['Hery', 'Halo']);
        });
        $this->assertFails(function () use ($response) {
            $response->assertSee('tidak ada');
        });
    }

    public function testJsonAssertions() {
        $payload = ['data' => ['id' => 7, 'name' => 'Apel', 'tags' => ['buah', 'merah']], 'meta' => ['total' => 1]];
        $response = $this->json($payload);
        $response->assertJson(['data' => ['id' => 7]])
            ->assertJsonPath('data.name', 'Apel')
            ->assertJsonPath('data.tags.1', 'merah')
            ->assertJsonFragment(['name' => 'Apel'])
            ->assertJsonMissing(['name' => 'Beras'])
            ->assertJsonStructure(['data' => ['id', 'name', 'tags'], 'meta' => ['total']])
            ->assertJsonCount(2, 'data.tags')
            ->assertExactJson($payload)
            ->assertSimilarJson(['meta' => ['total' => 1], 'data' => ['tags' => ['buah', 'merah'], 'name' => 'Apel', 'id' => 7]]);
        $this->assertSame('Apel', $response->json('data.name'));
        $this->assertSame(7, $response['data']['id'], 'ArrayAccess ke JSON');
        $this->assertSame($payload, $response->json());
        $this->assertFails(function () use ($response) {
            $response->assertJsonPath('data.id', 8);
        });
        $this->assertFails(function () use ($response) {
            $response->assertExactJson(['data' => ['id' => 7]]);
        });
        $this->assertFails(function () use ($response) {
            $response->assertJsonMissing(['name' => 'Apel']);
        });
    }

    public function testJsonValidationErrors() {
        $response = $this->json(['message' => 'gagal', 'errors' => ['email' => ['Email wajib diisi'], 'name' => ['Nama wajib']]], 422);
        $response->assertStatus(422)
            ->assertJsonValidationErrors('email')
            ->assertJsonValidationErrors(['email' => 'wajib diisi'])
            ->assertJsonMissingValidationErrors('phone');
        $this->assertFails(function () use ($response) {
            $response->assertJsonValidationErrors('phone');
        });
        $this->assertFails(function () use ($response) {
            $response->assertJsonMissingValidationErrors('email');
        });
        $this->json(['ok' => true])->assertJsonMissingValidationErrors();
    }

    public function testInvalidJsonBodyFails() {
        $this->assertFails(function () {
            $this->html('bukan json')->assertJson(['a' => 1]);
        });
    }

    public function testProxiesToTheBaseResponse() {
        $response = $this->html('isi', 202, ['X-A' => '1']);
        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('isi', $response->getContent());
        $this->assertSame('1', $response->headers->get('X-A'));
        $this->assertInstanceOf(CHTTP_Response::class, $response->baseResponse);
    }

    public function testAssertableJsonStringStandalone() {
        $json = new CTesting_AssertableJsonString('{"items":[{"id":1},{"id":2}],"ok":true}');
        $json->assertCount(2, 'items')->assertPath('items.0.id', 1)->assertFragment(['ok' => true])->assertStructure(['items' => ['*' => ['id']], 'ok']);
        $this->assertSame(2, count($json['items']));
        $this->assertTrue($json['ok']);
        $this->assertFails(function () use ($json) {
            $json->assertCount(3, 'items');
        });
        $fromArray = new CTesting_AssertableJsonString(['a' => 1]);
        $fromArray->assertExact(['a' => 1]);
    }
}
