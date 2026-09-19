<?php
use PHPUnit\Framework\TestCase;

/**
 * CEmail_Builder (RuntimeBuilder → komponen → Renderer): HTML yang dihasilkan valid, atribut
 * di-escape, lebar kolom/grup untuk Outlook benar, default lewat c-all diterapkan.
 */
class BuilderRenderTest extends TestCase {
    /**
     * Pohon email lengkap yang meniru pola buildEmailBuilder() di app.
     *
     * @return CEmail_Builder_RuntimeBuilder
     */
    protected function sampleBuilder() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $builder->addHead()->addHeadAttributes();
        $body = $builder->addBody()->setBackgroundColor('#C4C4C4')->setPadding('30px 0px')->setWidth('650px');

        $header = $body->addSection()->setBackgroundColor('#ffffff')->setPadding('20px 0')->setTextAlign('center');
        $header->addColumn()->addImage()->setAlign('center')->setSrc('https://example.test/logo.png?a=1&b=2')->setWidth('128px')->setAlt('Logo "Uji" <x>');
        $header->addDivider()->setBorderColor('#1a347b');

        $content = $body->addSection()->setBackgroundColor('#ffffff')->setPadding('0 0 24px 0')->setBorder('2px solid #ff0000');
        $left = $content->addColumn()->setWidth('50%');
        $left->addText()->setFontSize('16px')->setLineHeight('25px')->add('Hai Hery &amp; <b>Tim</b>,');
        $left->addButton()->setColor('#ffffff')->setBackgroundColor('#1a347b')->setHref('https://example.test/x?y=1&z=2')->add('Reset Password');
        $right = $content->addColumn()->setWidth('50%');
        $right->addText()->setPaddingTop('40px')->add('Kolom dua');

        $group = $body->addSection()->addGroup();
        $group->addColumn()->addText()->add('G1');
        $group->addColumn()->addText()->add('G2');

        return $builder;
    }

    /**
     * @param string $html
     *
     * @return array pesan error libxml
     */
    protected function libxmlErrors($html) {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $document = new DOMDocument();
        $document->loadHTML($html);
        $errors = array_map(function ($error) {
            return trim($error->message) . ' @' . $error->line;
        }, libxml_get_errors());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $errors;
    }

    public function testRenderedHtmlIsWellFormed() {
        $html = $this->sampleBuilder()->render();

        $this->assertStringStartsWith('<!doctype html>', $html);
        $this->assertSame([], $this->libxmlErrors($html), 'HTML hasil builder harus lolos parser tanpa error');
    }

    public function testAttributeValuesAreEscaped() {
        $html = $this->sampleBuilder()->render();

        $this->assertStringContainsString('alt="Logo &quot;Uji&quot; &lt;x&gt;"', $html, 'kutip dan tag di alt di-escape');
        $this->assertStringContainsString('src="https://example.test/logo.png?a=1&amp;b=2"', $html, '& di URL menjadi &amp;');
        $this->assertStringContainsString('href="https://example.test/x?y=1&amp;z=2"', $html);
        $this->assertStringContainsString('Hai Hery &amp; <b>Tim</b>,', $html, 'konten teks tidak di-escape ulang (memang HTML)');

        $builder = CEmail::builder()->createRuntimeBuilder();
        $builder->addBody()->addSection()->addColumn()->addImage()->setSrc('https://x.test/?a=1&amp;b=2');
        $this->assertStringContainsString('src="https://x.test/?a=1&amp;b=2"', $builder->render(), 'nilai yang sudah di-escape tidak di-escape dua kali');
    }

    public function testGroupColumnsGetTheirOwnOutlookWidth() {
        $html = $this->sampleBuilder()->render();
        $groupStart = strrpos($html, 'class="c-column-per-100 c-outlook-group-fix"');
        $this->assertNotFalse($groupStart);
        $groupHtml = substr($html, $groupStart);

        $this->assertSame(2, substr_count($groupHtml, 'width:325px'), 'dua kolom 50% dalam grup 650px = 325px untuk Outlook');
        $this->assertStringNotContainsString('15.38', $html);
    }

    public function testOutlookGroupFixClassIsConsistentAndStyled() {
        $html = $this->sampleBuilder()->render();

        $this->assertStringNotContainsString('mj-outlook-group-fix"', $html, 'kelas lama mj-* tidak dipakai di markup');
        $this->assertStringContainsString('class="c-column-per-50 c-outlook-group-fix"', $html);
        $this->assertStringContainsString('class="c-column-per-100 c-outlook-group-fix"', $html, 'grup memakai kelas yang sama dengan kolom');
        $this->assertMatchesRegularExpression('/\.mj-outlook-group-fix,\s*\.c-outlook-group-fix\s*\{\s*width:100% !important;/', $html, 'CSS Outlook <= 2013 tersedia untuk kedua nama kelas');
    }

    public function testWidthHtmlAttributesCarryNoUnit() {
        $html = $this->sampleBuilder()->render();

        $this->assertStringContainsString('width="128"', $html, 'atribut width <img> tanpa satuan');
        $this->assertStringNotContainsString('width="128px"', $html);
        $this->assertStringContainsString('width="650"', $html, 'tabel mso body tanpa satuan');
        $this->assertStringNotContainsString('width="650px"', $html);
        $this->assertStringContainsString('style="width:650px;"', $html, 'style tetap memakai px');
        $this->assertStringContainsString('width:128px;', $html);
    }

    public function testDefaultAttributesFromAllAndPerTagApplyToChildren() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $attributes = $builder->addHead()->addHeadAttributes();
        $attributes->addAll()->setFontFamily('Arial, sans-serif')->setColor('#123456');
        $attributes->addNode('c-text')->setFontSize('15px');
        $column = $builder->addBody()->addSection()->addColumn();
        $column->addText()->add('Teks');
        $column->addText()->setColor('#abcdef')->add('Teks dua');
        $column->addButton()->setHref('https://x.test')->add('Tombol');
        $html = $builder->render();

        $this->assertStringContainsString('font-family:Arial, sans-serif;font-size:15px;line-height:1;text-align:left;color:#123456;', $html, 'c-all lalu c-text diterapkan ke teks');
        $this->assertStringContainsString('font-size:15px;line-height:1;text-align:left;color:#abcdef;', $html, 'atribut node menang atas default');
        $this->assertMatchesRegularExpression('/<a href="https:\/\/x\.test"[^>]*font-family:Arial, sans-serif;/', $html, 'c-all juga berlaku untuk tombol');
        $this->assertStringNotContainsString('fonts.googleapis.com', $html, 'font Ubuntu bawaan tidak dipakai, tidak ada <link> Google Fonts');

        $plain = CEmail::builder()->createRuntimeBuilder();
        $plain->addBody()->addSection()->addColumn()->addText()->add('Default');
        $this->assertStringContainsString('font-family:Ubuntu, Helvetica, Arial, sans-serif;', $plain->render(), 'tanpa c-all, default komponen tidak berubah');
    }

    public function testChildPaddingInsideColumnUsesTheChildAttributes() {
        $html = $this->sampleBuilder()->render();

        $this->assertStringContainsString('padding:10px 25px;padding-top:40px;word-break:break-word;', $html, 'padding-top milik teks, bukan kolom');
    }

    public function testButtonWidthSubtractsRealBorderWidth() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $column = $builder->addBody()->addSection()->addColumn();
        $column->addButton()->setWidth('200px')->setBorder('4px solid #000000')->setHref('https://x.test')->add('A');
        $column->addButton()->setWidth('200px')->setHref('https://x.test')->add('B');
        $html = $builder->render();

        $this->assertStringContainsString('width:142px;', $html, '200 - inner padding 50 - border 2x4');
        $this->assertStringContainsString('width:150px;', $html, 'border none = 0, bukan 1px per sisi');
    }

    public function testPixelColumnMobileWidthIsAPercentageOfTheContainer() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $group = $builder->addBody()->setWidth('650px')->addSection()->setPadding('0')->addGroup();
        $group->addColumn()->setWidth('300px')->addText()->add('x');
        $group->addColumn()->setWidth('350px')->addText()->add('y');
        $html = $builder->render();

        $this->assertStringContainsString('class="c-column-px-300 c-outlook-group-fix"', $html);
        $this->assertMatchesRegularExpression('/display:inline-block;vertical-align:top;width:46\.15\d*%;/', $html, 'kolom px di dalam grup: 300/650 dari lebar grup');
        $this->assertStringContainsString('.c-column-px-300 { width:300px !important; max-width:300px; }', $html);

        $single = CEmail::builder()->createRuntimeBuilder();
        $single->addBody()->setWidth('650px')->addSection()->addColumn()->setWidth('300px')->addText()->add('x');
        $this->assertStringContainsString('display:inline-block;vertical-align:top;width:100%;', $single->render(), 'di luar grup kolom selalu 100% di mobile');
    }

    public function testImageWithHrefIsWrappedInAnAnchor() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $builder->addBody()->addSection()->addColumn()->addImage()->setSrc('https://x.test/a.png')->setHref('https://x.test/go')->setTarget('_blank');
        $html = $builder->render();

        $this->assertMatchesRegularExpression('/<a href="https:\/\/x\.test\/go" target="_blank">\s*<img /', $html);
    }

    public function testOwaDesktopStyleUsesRealNewlines() {
        $builder = $this->sampleBuilder();
        $builder->setOwa('desktop');
        $html = $builder->render();

        $this->assertStringContainsString('[owa] .c-column-per-50', $html);
        $this->assertStringNotContainsString('\n', $html, 'tidak ada literal backslash-n di HTML');
    }

    public function testMissingBodyThrowsAClearException() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $builder->addHead();

        $this->expectException(CEmail_Builder_Exception::class);
        $this->expectExceptionMessage('c-body');
        $builder->render();
    }

    public function testNoDoubleSpacesBeforeAttributesOrClosingBrackets() {
        $html = $this->sampleBuilder()->render();

        $this->assertDoesNotMatchRegularExpression('/<(p|table|td|div|a|img)\s{2,}/', $html);
        $this->assertDoesNotMatchRegularExpression('/"\s+>/', $html);
    }

    public function testToHtmlForwardsRenderOptions() {
        $cml = '<cml><c-body><c-section><c-column><c-text font-family="Ubuntu, Arial">Halo</c-text></c-column></c-section></c-body></cml>';

        $this->assertStringContainsString('fonts.googleapis.com/css?family=Ubuntu', CEmail::builder()->toHtml($cml));
        $this->assertStringNotContainsString('fonts.googleapis.com', CEmail::builder()->toHtml($cml, ['fonts' => []]), "opsi 'fonts' diteruskan ke parser");
    }
}
