<?php

/**
 * Description of UnitTestAbstract
 */

use \PHPUnit\Framework\TestCase;

abstract class CQC_UnitTestAbstract extends TestCase {
    use CQC_UnitTest_Trait_HttpRequestTrait;

    /**
     * @var CQC_UnitTest_AssertResultCollection
     */
    protected $assertResults;

    /**
     * This method is called before each test.
     *
     * @return void
     */
    public function setUp() {
    }

    /**
     * This method is called after each test.
     *
     * @return void
     */
    public function tearDown() {
    }

    public function build() {
        if ($this->assertResults) {
            unset($this->assertResults);
        }
        $this->assertResults = new CQC_UnitTest_AssertResultCollection();
    }

    public function destroy() {
    }

    public function result() {
        return $this->assertResults;
    }

    public function invoke($uri, $options) {
        $postTemporary = $_POST;
        $getTemporary = $_GET;
        $serverTemporary = $_SERVER;
        $haveNativeSession = true;

        if (isset($_SESSION)) {
            $sessionTemporary = $_SESSION;
        } else {
            $haveNativeSession = false;
            $sessionTemporary = CSession::instance()->get();
        }

        $post = carr::get($options, 'post', []);
        $get = carr::get($options, 'get', []);
        $server = carr::get($options, 'server', $_SERVER);
        $session = carr::get($options, 'session', []);
        $_POST = $post;
        $_GET = $get;
        $_SERVER = $server;
        $_SESSION = $session;
        try {
            ob_start();

            CF::invoke($uri);
            $result = ob_get_clean();
        } catch (Exception $ex) {
            $result = ob_get_clean();
            throw $ex;
        } finally {
            $_POST = $postTemporary;
            $_GET = $getTemporary;
            $_SERVER = $serverTemporary;
            if ($haveNativeSession) {
                $_SESSION = $sessionTemporary;
            } else {
                CSession::instance()->set($sessionTemporary);
            }
        }

        return $result;
    }
}
