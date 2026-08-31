<?php

use PHPUnit\Framework\TestCase;
use League\Flysystem\PathTraversalDetected;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A scanner probe hitting a temp-file path (imgupload, ajax, ...) with a
 * traversal payload like "../../../etc/passwd" gets refused by Flysystem's
 * own path normalizer before any adapter is touched - PathTraversalDetected
 * is the guard working correctly, not a bug. Left unmapped, the exception
 * handler treated it as a plain RuntimeException and rendered a 500,
 * flooding the exception collector with the same "attack" repeatedly.
 */
class PathTraversalDetectedHandlingTest extends TestCase {
    /**
     * @param \Throwable $e
     *
     * @return \Throwable
     */
    private function prepare($e) {
        $handler = new CException_ExceptionHandler();
        $method = new ReflectionMethod(CException_ExceptionHandler::class, 'prepareException');
        $method->setAccessible(true);

        return $method->invoke($handler, $e);
    }

    public function testPathTraversalDetectedIsMappedToNotFound() {
        $prepared = $this->prepare(PathTraversalDetected::forPath('imgupload/../../../etc/passwd'));

        $this->assertInstanceOf(NotFoundHttpException::class, $prepared);
        $this->assertSame(404, $prepared->getStatusCode());
    }

    /**
     * The original exception must survive as the previous one, so the real
     * offending path still shows up in logs/traces.
     */
    public function testTheOriginalExceptionIsKeptAsThePrevious() {
        $original = PathTraversalDetected::forPath('imgupload/../../../etc/passwd');
        $prepared = $this->prepare($original);

        $this->assertSame($original, $prepared->getPrevious());
    }

    /**
     * CApi's own handler has a second, independent copy of this mapping
     * (it does not delegate to CException_ExceptionHandler::prepareException()).
     */
    public function testCApiExceptionHandlerAlsoMapsItToNotFound() {
        $handler = new CApi_ExceptionHandler([], false);
        $handled = $handler->handle(CHTTP_Request::create('/api/whatever', 'GET'), PathTraversalDetected::forPath('imgupload/../../../etc/passwd'));

        $this->assertSame(404, $handled->getStatusCode());
    }

    /**
     * A genuine Flysystem read/write failure that isn't traversal-shaped
     * must keep behaving like an ordinary server error.
     */
    public function testAnUnrelatedRuntimeExceptionIsUntouched() {
        $original = new RuntimeException('disk is on fire');
        $prepared = $this->prepare($original);

        $this->assertSame($original, $prepared);
    }
}
