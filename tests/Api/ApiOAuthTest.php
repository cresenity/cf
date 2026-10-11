<?php

use PHPUnit\Framework\TestCase;

/**
 * CApi_OAuth::tokensCan() - method instance yang menyimpan ke properti instance
 * $scopes (bukan static::$scopes yang tidak ada, lihat #-21084).
 */
class ApiOAuthTest extends TestCase {
    public function testTokensCanStoresScopesOnTheInstanceWithoutFatal() {
        $oauth = new CApi_OAuth('cfapitest-oauth');

        $this->assertSame([], $oauth->scopes);
        $oauth->tokensCan(['place-orders' => 'Place orders', 'check-status' => 'Check order status']);

        $this->assertSame(['place-orders' => 'Place orders', 'check-status' => 'Check order status'], $oauth->scopes);
    }

    public function testTokensCanIsReflectedByScopesFor() {
        $oauth = new CApi_OAuth('cfapitest-oauth');
        $oauth->tokensCan(['place-orders' => 'Place orders']);

        $scopes = $oauth->scopesFor(['place-orders', 'tidak-ada']);

        $this->assertCount(1, $scopes);
        $this->assertInstanceOf(CApi_OAuth_Scope::class, $scopes[0]);
        $this->assertSame('place-orders', $scopes[0]->id);
        $this->assertSame('Place orders', $scopes[0]->description);
    }

    public function testTokensCanDoesNotAffectOtherInstances() {
        $first = new CApi_OAuth('cfapitest-oauth-a');
        $second = new CApi_OAuth('cfapitest-oauth-b');

        $first->tokensCan(['scope-a' => 'A']);

        $this->assertSame(['scope-a' => 'A'], $first->scopes);
        $this->assertSame([], $second->scopes, 'instance lain tidak boleh ikut berubah');
    }
}
