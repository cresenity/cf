<?php

use PHPUnit\Framework\TestCase;

/**
 * CHTTP_Kernel::handle(): exception menjadi respons, event RequestHandled terpancar, dan kernel menandai dirinya selesai.
 */
class HttpKernelHandleTest extends TestCase {
    protected function tearDown(): void {
        CEvent::dispatcher()->forget(CHTTP_Event_RequestHandled::class);
        parent::tearDown();
    }

    public function testAnUnknownUrlIsAnsweredWithA404ResponseInsteadOfAnException() {
        $kernel = new CHTTP_Kernel();

        $response = $kernel->handle(CHTTP_Request::create('https://contoh.test/jalur-yang-tidak-ada-' . uniqid(), 'GET'));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertTrue($kernel->isHandled());
    }

    public function testTheRequestHandledEventCarriesTheRequestAndTheResponse() {
        $captured = null;
        CEvent::dispatcher()->listen(CHTTP_Event_RequestHandled::class, function ($event) use (&$captured) {
            $captured = $event;
        });
        $request = CHTTP_Request::create('https://contoh.test/jalur-yang-tidak-ada-' . uniqid(), 'GET');

        $response = (new CHTTP_Kernel())->handle($request);

        $this->assertNotNull($captured);
        $this->assertSame($request, $captured->request);
        $this->assertSame($response, $captured->response);
    }

    public function testAKernelThatDidNotHandleAnythingIsNotMarkedHandled() {
        $this->assertFalse((new CHTTP_Kernel())->isHandled());
    }
}
