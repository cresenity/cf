<?php
use PHPUnit\Framework\TestCase;

/**
 * CTesting_Browser_ElementResolver::format() dan pageElements() — bagian selector yang tidak perlu Chrome.
 */
class BrowserElementResolverTest extends TestCase {
    /**
     * @param string $prefix
     *
     * @return CTesting_Browser_ElementResolver
     */
    protected function resolver($prefix = 'body') {
        return new CTesting_Browser_ElementResolver(null, $prefix);
    }

    public function testFormatPrefixesWithBody() {
        $this->assertSame('body .tombol', $this->resolver()->format('.tombol'));
        $this->assertSame('#modal input[name=email]', $this->resolver('#modal')->format('input[name=email]'));
        $this->assertSame('body', $this->resolver()->format(''));
    }

    public function testDuskShorthandBecomesAttributeSelector() {
        $this->assertSame('body [dusk="login-button"]', $this->resolver()->format('@login-button'));
    }

    public function testPageElementsAreSubstitutedLongestFirst() {
        $resolver = $this->resolver()->pageElements([
            '@email' => 'input#email',
            '@email-error' => '.error.email',
            '@submit' => 'button[type=submit]',
        ]);
        $this->assertSame('body input#email', $resolver->format('@email'));
        $this->assertSame('body .error.email', $resolver->format('@email-error'), 'kunci terpanjang menang, bukan awalan @email');
        $this->assertSame('body form button[type=submit]', $resolver->format('form @submit'));
        $this->assertSame('body [dusk="lain"]', $resolver->format('@lain'), 'yang tidak terdaftar tetap jadi [dusk=…]');
        $this->assertSame(['@email' => 'input#email', '@email-error' => '.error.email', '@submit' => 'button[type=submit]'], $resolver->elements);
    }

    public function testOperatingSystemHelpersAgreeWithPhp() {
        $id = CTesting_Browser_OperatingSystem::id();
        $this->assertContains($id, ['linux', 'mac', 'win'], $id);
        $this->assertSame(PHP_OS === 'WINNT' || cstr::contains(php_uname(), 'Microsoft'), CTesting_Browser_OperatingSystem::onWindows());
        $this->assertSame(PHP_OS === 'Darwin', CTesting_Browser_OperatingSystem::onMac());
    }
}
