<?php
use PHPUnit\Framework\TestCase;

/**
 * League\Event (dependensi League\OAuth2 server) dimuat dari system/vendor, bukan modules/ yang deprecated.
 */
class LeagueEventAutoloadTest extends TestCase {
    public function testLeagueEventLoadsFromSystemVendor() {
        $this->assertTrue(class_exists(League\Event\Emitter::class));
        $file = (new ReflectionClass(League\Event\Emitter::class))->getFileName();
        $this->assertStringStartsWith(SYSPATH . 'vendor' . DS . 'League' . DS . 'Event' . DS, $file);
        $this->assertDirectoryDoesNotExist(DOCROOT . 'modules/cresenity/vendor/League');
        $emitter = new League\Event\Emitter();
        $this->assertInstanceOf(League\Event\EmitterInterface::class, $emitter);
        $server = new ReflectionClass(League\OAuth2\Server\AuthorizationServer::class);
        $this->assertContains(League\Event\EmitterAwareTrait::class, $server->getTraitNames(), 'AuthorizationServer memakai EmitterAwareTrait dari League\\Event');
    }
}
