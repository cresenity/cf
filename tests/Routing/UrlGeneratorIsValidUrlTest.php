<?php

use PHPUnit\Framework\TestCase;

/**
 * asset()/media() call isValidUrl($path) to decide whether to pass a path
 * through untouched. An array-typed $path (e.g. a scanner sending
 * "?ref[]=x" that ends up threaded into an asset path) used to crash
 * preg_match() with a TypeError instead of just being treated as "not a
 * valid URL".
 */
class UrlGeneratorIsValidUrlTest extends TestCase {
    public function testAnArrayPathIsNotAValidUrl() {
        $generator = new CRouting_UrlGenerator();

        $this->assertFalse($generator->isValidUrl(['a', 'b']));
        $this->assertFalse($generator->isValidUrl([]));
    }

    /**
     * The existing scalar behavior must survive the guard untouched.
     */
    public function testScalarBehaviorIsUnchanged() {
        $generator = new CRouting_UrlGenerator();

        $this->assertTrue($generator->isValidUrl('https://example.com/x.png'));
        $this->assertFalse($generator->isValidUrl('not a url'));
    }
}
