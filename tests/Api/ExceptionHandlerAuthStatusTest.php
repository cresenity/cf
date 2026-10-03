<?php

use PHPUnit\Framework\TestCase;

/**
 * CApi_ExceptionHandler mapped CAuth_Exception_AuthenticationException to HTTP
 * 401 in three places but had no equivalent for CAuth_Exception_AuthorizationException,
 * so a permission denial fell through to the generic 500 default (#RS-20766) -
 * confirmed against real dev traffic (a FOS user hitting an org-admin-only
 * endpoint got http=500 with errCode 500, instead of 403).
 */
class ExceptionHandlerAuthStatusTest extends TestCase {
    private static $defaultFormat = [
        'errCode' => ':code',
        'errMessage' => ':message',
        'data' => [
            'message' => ':message',
            'errors' => ':errors',
            'code' => ':code',
            'status_code' => ':status_code',
            'debug' => ':debug',
        ],
    ];

    /**
     * @return CApi_ExceptionHandler
     */
    private function handler() {
        return new CApi_ExceptionHandler(self::$defaultFormat, false);
    }

    public function testAuthorizationExceptionRespondsWith403() {
        $response = $this->handler()->handle(null, new CAuth_Exception_AuthorizationException('This account does not have access to this resource'));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(403, json_decode($response->getContent(), true)['errCode']);
    }

    /**
     * Regression guard: the existing 401 mapping must keep working alongside
     * the new 403 one.
     */
    public function testAuthenticationExceptionStillRespondsWith401() {
        $response = $this->handler()->handle(null, new CAuth_Exception_AuthenticationException());

        $this->assertSame(401, $response->getStatusCode());
    }

    /**
     * An ordinary unexpected error must keep falling through to 500 - the fix
     * is specific to the two auth exception classes, not a blanket change.
     */
    public function testAnOrdinaryExceptionStillRespondsWith500() {
        $response = $this->handler()->handle(null, new RuntimeException('boom'));

        $this->assertSame(500, $response->getStatusCode());
    }
}
