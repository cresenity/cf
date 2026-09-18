<?php
use PHPUnit\Framework\TestCase;

/**
 * chtml - pembangun tag/atribut HTML dan escaping.
 */
class chtmlTest extends TestCase {
    public function testSpecialchars() {
        $this->assertSame('&lt;a href=&quot;x&quot;&gt;O&#039;Neil &amp; co&lt;/a&gt;', chtml::specialchars('<a href="x">O\'Neil & co</a>'));
        $this->assertSame('&amp;amp;', chtml::specialchars('&amp;'), 'entitas yang sudah ada di-encode lagi (default)');
        $this->assertSame('&amp;', chtml::specialchars('&amp;', false), 'double_encode=false membiarkan entitas');
        $this->assertSame('123', chtml::specialchars(123), 'non-string dipaksa jadi string');
        $this->assertSame('', chtml::specialchars(null));
    }

    public function testSpecialurlencodeReplacesSpaces() {
        $this->assertSame('a%20b&amp;c', chtml::specialurlencode('a b&c'));
    }

    public function testAttributes() {
        $this->assertSame('', chtml::attributes([]));
        $this->assertSame('', chtml::attributes(null));
        $this->assertSame(' class="x"', chtml::attributes('class="x"'), 'string diteruskan dengan spasi depan');
        $this->assertSame(' id="a" data-x="1 &amp; 2"', chtml::attributes(['id' => 'a', 'data-x' => '1 & 2']));
    }

    public function testAnchorForIdTargetAndExternalUrl() {
        $this->assertSame('<a href="#top">Ke atas</a>', chtml::anchor('#top', 'Ke atas'));
        $this->assertSame('<a href="https://example.com/x?a=1" class="ext">Contoh</a>', chtml::anchor('https://example.com/x?a=1', 'Contoh', ['class' => 'ext']));
        $this->assertSame('<a href="https://example.com/">https://example.com/</a>', chtml::anchor('https://example.com/'), 'judul kosong = url');
        $this->assertSame('<a href="https://example.com/">&lt;b&gt;</a>', chtml::anchor('https://example.com/', '<b>', null, null, true), 'escape_title');
        $this->assertSame('<a href="https://example.com/"><b>x</b></a>', chtml::anchor('https://example.com/', '<b>x</b>'), 'judul default tidak di-escape');
    }

    public function testAnchorWindowedUrlsAddsTarget() {
        $original = chtml::$windowed_urls;
        chtml::$windowed_urls = true;
        try {
            $this->assertSame('<a href="https://example.com/" target="_blank">x</a>', chtml::anchor('https://example.com/', 'x', []));
            $this->assertSame('<a href="https://example.com/" target="_self">x</a>', chtml::anchor('https://example.com/', 'x', ['target' => '_self']), 'target eksplisit dipertahankan');
        } finally {
            chtml::$windowed_urls = $original;
        }
    }

    public function testAnchorForRelativeUriUsesSiteBase() {
        $html = chtml::anchor('user/edit/1?x=1#f', 'Edit');
        $this->assertStringStartsWith('<a href="', $html);
        $this->assertStringEndsWith('user/edit/1?x=1#f">Edit</a>', $html);
        $this->assertSame(curl::site('user/edit/1?x=1#f'), substr($html, 9, strpos($html, '">') - 9));
    }

    public function testAnchorArray() {
        $anchors = chtml::anchor_array(['https://a.example/' => 'A', '#b' => 'B']);
        $this->assertSame(['<a href="https://a.example/">A</a>', '<a href="#b">B</a>'], $anchors);
    }

    public function testMailtoObfuscatesTheAddress() {
        $html = chtml::mailto('hery@example.com', 'Kirim', ['class' => 'mail']);
        $this->assertStringStartsWith('<a href="&#109;&#097;&#105;&#108;&#116;&#111;&#058;', $html, 'mailto: selalu ter-encode');
        $this->assertStringEndsWith('" class="mail">Kirim</a>', $html);
        $this->assertStringNotContainsString('hery@example.com', $html, 'alamat mentah tidak muncul');
        $this->assertSame('Kirim', chtml::mailto('', 'Kirim'), 'email kosong = judul saja');
        $this->assertStringContainsString('?subject=Halo%20dunia', chtml::mailto('a@b.co?subject=Halo dunia', 'x'), 'parameter tidak di-obfuscate, spasi jadi %20');
    }

    public function testEmailObfuscationDecodesBackToTheAddress() {
        $safe = chtml::email('hery@example.com');
        $this->assertSame('hery@example.com', html_entity_decode($safe, ENT_QUOTES, 'UTF-8'));
        $this->assertStringContainsString('&#', $safe, '@ selalu di-encode (kasus 1 atau 2)');
    }

    public function testMeta() {
        $this->assertSame('<meta name="description" content="Halo" />', chtml::meta('description', 'Halo'));
        $this->assertSame('<meta http-equiv="content-type" content="text/html" />', chtml::meta('content-type', 'text/html'), 'tag http-equiv dari config http.meta_equiv');
        $this->assertSame("<meta name=\"a\" content=\"1\" />\n<meta name=\"b\" content=\"2\" />", chtml::meta(['a' => '1', 'b' => '2']));
    }

    public function testScriptAndStylesheetAddSuffixAndBase() {
        $base = curl::base(false);
        $this->assertSame('<script type="text/javascript" src="' . $base . 'js/app.js"></script>' . "\n", chtml::script('js/app'));
        $this->assertSame('<script type="text/javascript" src="https://cdn.example/x.js"></script>' . "\n", chtml::script('https://cdn.example/x.js'));
        $this->assertSame(chtml::script('a') . chtml::script('b') . "\n", chtml::script(['a', 'b']), 'bentuk array menambah satu baris kosong di akhir');
        $this->assertSame('<link rel="stylesheet" type="text/css" href="' . $base . 'css/app.css" />' . "\n", chtml::stylesheet('css/app'));
        $this->assertSame('<link rel="stylesheet" type="text/css" href="' . $base . 'css/print.css" media="print" />' . "\n", chtml::stylesheet('css/print.css', 'print'));
    }

    public function testImage() {
        $base = curl::base(false);
        $this->assertSame('<img src="' . $base . 'img/a.png" alt="Gambar" />', chtml::image('img/a.png', 'Gambar'));
        $this->assertSame('<img src="https://cdn.example/a.png" />', chtml::image('https://cdn.example/a.png'));
        $this->assertSame('<img src="' . $base . 'a.png" width="10" />', chtml::image(['src' => 'a.png', 'width' => 10]));
        $this->assertSame('<img src="' . $base . 'a.png" alt="x" class="c" />', chtml::image('a.png', ['alt' => 'x', 'class' => 'c']), 'alt boleh array atribut');
    }
}
