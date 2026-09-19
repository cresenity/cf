<?php
use PHPUnit\Framework\TestCase;

/**
 * CEmail_Builder per komponen: Body, Section (background/full-width), Column (gutter, kelas media query),
 * Group, Text, Image, Button, Divider, Social, Raw, head Style/Attributes, font, lang, Node API,
 * type adapter, Helper, parser CML, dan isolasi antar render.
 */
class BuilderComponentsTest extends TestCase {
    /**
     * @param int|string $bodyWidth
     *
     * @return array [CEmail_Builder_RuntimeBuilder, CEmail_Builder_Node kolom]
     */
    protected function columnIn($bodyWidth = '600px') {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $column = $builder->addBody()->setWidth($bodyWidth)->addSection()->addColumn();

        return [$builder, $column];
    }

    /**
     * @param string $html
     *
     * @return string
     */
    protected function squash($html) {
        return trim(preg_replace('/\s+/', ' ', $html));
    }

    public function testBodyDefaultsAndBackground() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $builder->addBody()->addSection()->addColumn()->addText()->add('x');
        $html = $builder->render();

        $this->assertStringContainsString('max-width:600px;', $html, 'lebar body bawaan 600px');
        $this->assertStringContainsString('width="600"', $html);
        $this->assertStringContainsString('<body>', $html, 'tanpa background-color body polos');

        $builder = CEmail::builder()->createRuntimeBuilder();
        $builder->addBody()->setBackgroundColor('#abc')->setPadding('20px 0')->addSection()->addColumn()->addText()->add('x');
        $html = $builder->render();
        $this->assertStringContainsString('<body style="background-color:#aabbcc;">', $html, 'warna pendek #abc dinormalkan oleh ColorAdapter');
        $this->assertStringContainsString('<div style="background-color:#aabbcc;padding:20px 0;">', $html);
    }

    public function testSectionWithBackgroundImageRendersVmlFallback() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $section = $builder->addBody()->addSection()->setBackgroundColor('#ffffff')->setBackgroundUrl('https://x.test/bg.png?a=1&b=2')->setBackgroundRepeat('no-repeat')->setBackgroundSize('cover');
        $section->addColumn()->addText()->add('x');
        $html = $builder->render();

        $this->assertStringContainsString('background:#ffffff url(https://x.test/bg.png?a=1&amp;b=2) top center / cover no-repeat;', $html, 'shorthand background sesuai MJML');
        $this->assertStringContainsString('background-position:top center;background-repeat:no-repeat;background-size:cover;', $html);
        $this->assertStringContainsString('<v:rect style="width:600px;" xmlns:v="urn:schemas-microsoft-com:vml" fill="true" stroke="false">', $html, 'VML untuk Outlook');
        $this->assertStringContainsString('<v:fill origin="0.5, 0" position="0.5, 0" src="https://x.test/bg.png?a=1&amp;b=2" color="#ffffff" type="tile"/>', $html);
        $this->assertStringContainsString('background="https://x.test/bg.png?a=1&amp;b=2"', $html, 'atribut background tabel');
        $this->assertStringContainsString('<div style="line-height:0;font-size:0;">', $html, 'inner div pembungkus konten');
    }

    public function testFullWidthSectionSpansTheWholeEmail() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $section = $builder->addBody()->addSection()->setFullWidth('full-width')->setBackgroundColor('#123456')->setCssClass('hero');
        $section->addColumn()->addText()->add('x');
        $html = $builder->render();

        $this->assertStringContainsString('<table align="center" class="hero" border="0" cellpadding="0" cellspacing="0" role="presentation" style="background:#123456;background-color:#123456;width:100%;">', $html, 'tabel luar 100% membawa background dan css-class');
        $this->assertMatchesRegularExpression('/<div style="margin:0px auto;max-width:600px;">/', $html, 'div dalam tetap dibatasi lebar container tanpa background');

        $withImage = CEmail::builder()->createRuntimeBuilder();
        $withImage->addBody()->addSection()->setFullWidth('full-width')->setBackgroundUrl('https://x.test/bg.png')->addColumn()->addText()->add('x');
        $fullHtml = $withImage->render();
        $this->assertStringContainsString('background="https://x.test/bg.png"', $fullHtml, 'background-url pindah ke tabel luar');
        $this->assertStringContainsString('mso-width-percent:1000;', $fullHtml, 'VML full-width');
    }

    public function testSectionDirectionBorderRadiusAndPaddings() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $builder->addBody()->addSection()->setDirection('rtl')->setBorderRadius('8px')->setPaddingTop('5px')->setPaddingBottom('6px')->setTextAlign('left')->addColumn()->addText()->add('x');
        $html = $builder->render();

        $this->assertStringContainsString('direction:rtl;font-size:0px;padding:20px 0;padding-bottom:6px;padding-top:5px;text-align:left;', $html);
        $this->assertStringContainsString('border-radius:8px;max-width:600px;', $html);
    }

    public function testColumnsDefaultToEqualWidthsWithOneMediaQueryPerClass() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $section = $builder->addBody()->addSection();
        foreach (['a', 'b', 'c'] as $text) {
            $section->addColumn()->addText()->add($text);
        }
        $html = $builder->render();

        $this->assertSame(3, substr_count($html, 'class="c-column-per-33-333333333333 c-outlook-group-fix"'), 'tiga kolom = 33.33% masing-masing, titik menjadi -');
        $this->assertSame(3, substr_count($html, '<td style="vertical-align:top;width:200px;">'), 'lebar Outlook 600/3');
        $this->assertSame(1, substr_count($html, '.c-column-per-33-333333333333 { width:33.333333333333% !important; max-width:33.333333333333%; }'), 'media query ditulis sekali per kelas');
    }

    public function testColumnGutterBackgroundAndBorder() {
        list($builder, $column) = $this->columnIn();
        $column->setPadding('12px')->setBackgroundColor('#eeeeee')->setBorder('1px solid #cccccc')->setBorderRadius('4px')->setVerticalAlign('middle');
        $column->addText()->add('x');
        $html = $builder->render();

        $this->assertStringContainsString('<td style="background-color:#eeeeee;border:1px solid #cccccc;border-radius:4px;vertical-align:middle;padding:12px;">', $html, 'padding kolom = gutter, membungkus tabel isi');
        $this->assertStringContainsString('<table border="0" cellpadding="0" cellspacing="0" role="presentation" width="100%">', $html, 'tabel gutter');
        $this->assertStringContainsString('display:inline-block;vertical-align:middle;width:100%;', $html);
        $this->assertStringContainsString('<td style="vertical-align:middle;width:600px;">', $html, 'sel Outlook mengikuti vertical-align');
    }

    public function testGroupWithThreeColumnsAndExplicitWidth() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $group = $builder->addBody()->addSection()->setPadding('0')->addGroup()->setWidth('50%')->setBackgroundColor('#fafafa');
        $group->addColumn()->addText()->add('a');
        $group->addColumn()->addText()->add('b');
        $html = $builder->render();

        $this->assertStringContainsString('class="c-column-per-50 c-outlook-group-fix" style="font-size:0;line-height:0;text-align:left;display:inline-block;width:100%;direction:ltr;background-color:#fafafa;">', $html, 'grup 50%');
        $this->assertStringContainsString('<td style="width:300px;">', $html, 'sel Outlook grup 50% dari 600');
        $this->assertSame(2, substr_count($html, '<td style="font-size:0px;vertical-align:top;width:150px;">'), 'kolom dalam grup: 50% dari 300px');
        $this->assertSame(2, substr_count($html, 'display:inline-block;vertical-align:top;width:50%;'), 'kolom dalam grup memakai lebar % di mobile');
    }

    public function testTextAttributesHeightAndContainerBackground() {
        list($builder, $column) = $this->columnIn();
        $column->addText()->setAlign('center')->setFontStyle('italic')->setFontWeight('700')->setLetterSpacing('-1px')->setTextTransform('uppercase')->setTextDecoration('underline')->setHeight('100px')->setContainerBackgroundColor('#ffeeaa')->add('Teks');
        $html = $builder->render();

        $this->assertStringContainsString('<td align="center" style="background:#ffeeaa;font-size:0px;padding:10px 25px;word-break:break-word;">', $html, 'container-background-color di td');
        $this->assertStringContainsString('font-style:italic;font-weight:700;letter-spacing:-1px;line-height:1;text-align:center;text-decoration:underline;text-transform:uppercase;color:#000000;height:100px;', $html);
        $this->assertStringContainsString('<td height="100px" style="vertical-align:top;height:100px;">', $html, 'height memakai tabel bersyarat mso');
        $this->assertStringContainsString('<!--[if mso | IE]>', $html);
    }

    public function testImageAttributesFluidOnMobileAndWidthClamp() {
        list($builder, $column) = $this->columnIn();
        $column->addImage()->setSrc('https://x.test/a.png')->setSrcset('https://x.test/a@2x.png 2x')->setTitle('Judul')->setHeight('40px')->setBorderRadius('6px')->setFluidOnMobile(true)->setWidth('700px')->setPadding('0');
        $html = $builder->render();

        $this->assertStringContainsString('class="c-full-width-mobile"', $html);
        $this->assertStringContainsString('srcset="https://x.test/a@2x.png 2x"', $html);
        $this->assertStringContainsString('title="Judul"', $html);
        $this->assertStringContainsString('height="40"', $html, 'height tanpa satuan di atribut');
        $this->assertStringContainsString('border-radius:6px;', $html);
        $this->assertStringContainsString('width="600"', $html, 'gambar 700px dibatasi lebar kotak 600 (padding 0)');
        $this->assertStringContainsString('<td style="width:600px;" class="c-full-width-mobile">', $html);
    }

    public function testButtonAlignmentLinkAttributesAndPercentWidth() {
        list($builder, $column) = $this->columnIn();
        $column->addButton()->setAlign('left')->setHref('https://x.test/go')->setRel('noopener')->setName('cta')->setTarget('_self')->setInnerPadding('12px 30px')->setWidth('50%')->setFontWeight('bold')->setBorderRadius('0px')->add('Klik');
        $html = $builder->render();

        $this->assertStringContainsString('<td align="left" vertical-align="middle" style="font-size:0px;padding:10px 25px;word-break:break-word;">', $html);
        $this->assertStringContainsString('<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:separate;width:50%;line-height:100%;">', $html, 'lebar % dipakai di tabel');
        $this->assertMatchesRegularExpression('/<a href="https:\/\/x\.test\/go" rel="noopener" name="cta" style="[^"]*font-weight:bold;[^"]*padding:12px 30px;[^"]*" target="_self">/', $html);
        $this->assertDoesNotMatchRegularExpression('/<a href="https:\/\/x\.test\/go"[^>]*style="[^"]*width:/', $html, 'lebar % tidak dihitung ke <a>');
        $this->assertStringContainsString('mso-padding-alt:12px 30px;', $html);
    }

    public function testDividerWidthsAndStyle() {
        list($builder, $column) = $this->columnIn();
        $column->addDivider()->setBorderColor('#ff0000')->setBorderStyle('dashed')->setBorderWidth('2px')->setWidth('50%');
        $column->addDivider()->setWidth('300px')->setPadding('0');
        $html = $builder->render();

        $this->assertStringContainsString('<p style="border-top:dashed 2px #ff0000;font-size:1px;margin:0px auto;width:50%;">', $html);
        $this->assertStringContainsString('style="border-top:dashed 2px #ff0000;font-size:1px;margin:0px auto;width:250px;" role="presentation" width="250"', $html, 'Outlook: 600*50% - padding 50');
        $this->assertStringContainsString('style="border-top:solid 4px #000000;font-size:1px;margin:0px auto;width:300px;" role="presentation" width="300"', $html, 'lebar px dipakai apa adanya');
    }

    public function testSocialElementsRenderIconsAndShareLinks() {
        list($builder, $column) = $this->columnIn();
        $social = $column->addNode('c-social')->setMode('horizontal')->setIconSize('30px');
        $social->addNode('c-social-element')->setName('facebook')->setHref('https://x.test/artikel')->add('Bagikan');
        $social->addNode('c-social-element')->setName('facebook-noshare')->setHref('https://facebook.com/cresenity');
        $social->addNode('c-social-element')->setName('instagram')->setHref('https://instagram.com/cresenity')->setSrc('https://x.test/ig.png');
        $html = $builder->render();

        $this->assertStringContainsString('src="https://www.mailjet.com/images/theme/v1/icons/ico-social/facebook.png"', $html, 'ikon bawaan jaringan');
        $this->assertStringContainsString('href="https://www.facebook.com/sharer/sharer.php?u=https://x.test/artikel"', $html, 'share-url jaringan dengan [[URL]] diisi href');
        $this->assertStringContainsString('href="https://facebook.com/cresenity"', $html, 'varian -noshare memakai href apa adanya');
        $this->assertStringContainsString('src="https://x.test/ig.png"', $html, 'src eksplisit menimpa ikon bawaan');
        $this->assertStringContainsString('background:#3b5998;', $html, 'warna latar jaringan');
        $this->assertStringContainsString('height="30" src=', $html);
        $this->assertStringContainsString('Bagikan', $html);
        $this->assertStringContainsString('display:inline-table;', $html, 'mode horizontal');

        list($builder, $column) = $this->columnIn();
        $column->addNode('c-social')->setMode('vertical')->addNode('c-social-element')->setName('github');
        $this->assertStringContainsString('<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:0px;">', $builder->render(), 'mode vertikal satu tabel');
    }

    public function testRawContentIsEmittedVerbatimAndNotCountedAsASibling() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $section = $builder->addBody()->addSection();
        $section->addColumn()->addText()->add('a');
        $section->addNode('c-raw')->add('<!-- penanda --><div class="mentah">&nbsp;</div>');
        $section->addColumn()->addText()->add('b');
        $html = $builder->render();

        $this->assertStringContainsString('<!-- penanda --><div class="mentah">&nbsp;</div>', $html);
        $this->assertSame(2, substr_count($html, 'class="c-column-per-50 c-outlook-group-fix"'), 'raw tidak dihitung: dua kolom tetap 50%');
    }

    public function testHeadStyleAndPerTagAttributes() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $head = $builder->addHead();
        $head->addNode('c-style')->add('.promo { color: red; }');
        $head->addNode('c-style')->setInline('inline')->add('.inline-only { color: blue; }');
        $head->addHeadAttributes()->addNode('c-section')->setPadding('0px')->setBackgroundColor('#f0f0f0');
        $builder->addBody()->addSection()->addColumn()->addText()->add('x');
        $html = $builder->render();

        $this->assertStringContainsString('.promo { color: red; }', $html);
        $this->assertStringNotContainsString('.inline-only', $html, 'style inline tidak ditulis ke head');
        $this->assertStringContainsString('font-size:0px;padding:0px;text-align:center;', $html, 'default c-section dari head');
        $this->assertStringContainsString('background:#f0f0f0;background-color:#f0f0f0;', $html);
    }

    public function testFontLinksOnlyForFontsActuallyUsed() {
        list($builder, $column) = $this->columnIn();
        $column->addText()->setFontFamily('Roboto, Arial')->add('x');
        $column->addButton()->setFontFamily('Lato')->setHref('#')->add('y');
        $html = $builder->render();

        $this->assertStringContainsString('family=Roboto', $html);
        $this->assertStringContainsString('family=Lato', $html);
        $this->assertStringNotContainsString('family=Open+Sans', $html);
        $this->assertStringNotContainsString('family=Droid', $html);

        $custom = $builder->render(['fonts' => ['Poppins' => 'https://fonts.test/poppins.css']]);
        $this->assertStringNotContainsString('googleapis', $custom, 'daftar font kustom menggantikan bawaan');
        $this->assertStringNotContainsString('poppins', $custom, 'Poppins tidak dipakai, tidak diimpor');

        list($builder, $column) = $this->columnIn();
        $column->addText()->setFontFamily('Poppins')->add('x');
        $this->assertStringContainsString('<link href="https://fonts.test/poppins.css" rel="stylesheet" type="text/css">', $builder->render(['fonts' => ['Poppins' => 'https://fonts.test/poppins.css']]));
    }

    public function testLangAttributeOnHtmlTag() {
        list($builder, $column) = $this->columnIn();
        $column->addText()->add('x');
        $builder->setLang('id');

        $this->assertStringContainsString('<html lang="id" xmlns=', $builder->render());
    }

    public function testNodeApi() {
        $node = new CEmail_Builder_Node(['tagName' => 'c-text', 'attributes' => ['color' => '#fff']]);
        $this->assertSame('text', $node->getComponentName());
        $this->assertSame('c-text', $node->getTagName());
        $node->setBackgroundColor('#000')->setFontSize('12px')->setAttr('css-class', 'x');
        $this->assertSame(['color' => '#fff', 'background-color' => '#000', 'font-size' => '12px', 'css-class' => 'x'], $node->getAttributes(), 'set* → atribut kebab-case');
        $node->add('Halo ')->add('dunia');
        $this->assertSame('Halo dunia', $node->getContent(), 'string ditambahkan ke konten');
        $child = $node->addNode('c-raw');
        $this->assertSame([$child], $node->getChildren());
        $this->assertSame($node, $node->add(new CEmail_Builder_Node(['tagName' => 'c-raw'])), 'add() node mengembalikan induk');

        $this->expectException(Exception::class);
        $node->add(123);
    }

    public function testRuntimeBuilderForwardsOnlyKnownCalls() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $this->assertInstanceOf(CEmail_Builder_Node::class, $builder->addBody());
        $this->assertSame($builder->getChildren()[0], $builder->getChildren()[0]);

        $this->expectException(Exception::class);
        $builder->bukanMethod();
    }

    public function testHelperParsers() {
        $this->assertSame(['parsedWidth' => 50, 'unit' => '%'], CEmail_Builder_Helper::widthParser('50%'));
        $this->assertSame(['parsedWidth' => 300, 'unit' => 'px'], CEmail_Builder_Helper::widthParser('300'));
        $this->assertSame(['parsedWidth' => 12, 'unit' => 'px'], CEmail_Builder_Helper::widthParser('12.7px'));
        $this->assertSame('4px', CEmail_Builder_Helper::shorthandParser('1px 2px 3px 4px', 'left'));
        $this->assertSame('3px', CEmail_Builder_Helper::shorthandParser('1px 2px 3px 4px', 'bottom'));
        $this->assertSame('2px', CEmail_Builder_Helper::shorthandParser('1px 2px', 'right'));
        $this->assertSame('1px', CEmail_Builder_Helper::shorthandParser('1px 2px', 'bottom'));
        $this->assertSame('1px', CEmail_Builder_Helper::shorthandParser('1px 2px 3px', 'top'));
        $this->assertSame('3px', CEmail_Builder_Helper::shorthandParser('1px 2px 3px', 'bottom'));
        $this->assertSame('9px', CEmail_Builder_Helper::shorthandParser('9px', 'left'), 'satu nilai berlaku semua sisi');
        $this->assertSame('2', CEmail_Builder_Helper::borderParser('2px solid red'));
        $this->assertSame(0, CEmail_Builder_Helper::borderParser('none'));
        $this->assertSame('color:#fff;padding:0;', CEmail_Builder_Helper::renderStyle(['color' => '#fff', 'x' => null, 'y' => '', 'padding' => '0']));
    }

    public function testTypeAdaptersNormalizeValues() {
        $this->assertSame(CEmail_Builder_Type_Adapter_ColorAdapter::class, CEmail_Builder_Type_TypeFactory::getAdapter('color'));
        $this->assertSame(CEmail_Builder_Type_Adapter_UnitAdapter::class, CEmail_Builder_Type_TypeFactory::getAdapter('unit(px,%){1,4}'));
        $this->assertSame(CEmail_Builder_Type_Adapter_UnitAdapter::class, CEmail_Builder_Type_TypeFactory::getAdapter('unitWithNegative(px,%)'));
        $this->assertSame(CEmail_Builder_Type_Adapter_EnumAdapter::class, CEmail_Builder_Type_TypeFactory::getAdapter('enum(left,right)'));
        $this->assertSame(CEmail_Builder_Type_Adapter_BooleanAdapter::class, CEmail_Builder_Type_TypeFactory::getAdapter('boolean'));
        $this->assertSame(CEmail_Builder_Type_Adapter_StringAdapter::class, CEmail_Builder_Type_TypeFactory::getAdapter('string'));

        $this->assertSame('#aabbcc', (new CEmail_Builder_Type_Adapter_ColorAdapter('color', '#abc'))->getValue());
        $this->assertSame('#a1b2c3', (new CEmail_Builder_Type_Adapter_ColorAdapter('color', '#a1b2c3'))->getValue());
        $this->assertSame('rgb(1, 2, 3)', (new CEmail_Builder_Type_Adapter_ColorAdapter('color', 'rgb(1, 2, 3)'))->getValue());
        $this->assertSame(['color' => '#aabbcc', 'padding' => '1px', 'lain' => 'tetap'], CEmail_Builder_Helper::formatAttributes(['color' => '#abc', 'padding' => '1px', 'lain' => 'tetap'], ['color' => 'color', 'padding' => 'unit(px)']), 'atribut di luar daftar diloloskan apa adanya');

        $this->expectException(Exception::class);
        CEmail_Builder_Type_TypeFactory::getAdapter('tipe-asing');
    }

    public function testCmlParserBuildsTheSameTreeAsTheRuntimeBuilder() {
        $cml = '<cml lang="id">'
            . '<c-head><c-attributes><c-all font-family="Arial, sans-serif"/></c-attributes></c-head>'
            . '<c-body width="500px">'
            . '<c-section background-color="#fff"><c-column width="50%"><c-text color="#123456">Halo &amp; <b>dunia</b></c-text></c-column>'
            . '<c-column><c-button href="https://x.test/?a=1&amp;b=2">Klik</c-button></c-column></c-section>'
            . '</c-body></cml>';
        $html = CEmail::builder()->toHtml($cml);

        $this->assertStringContainsString('<html lang="id"', $html);
        $this->assertStringContainsString('max-width:500px;', $html);
        $this->assertStringContainsString('font-family:Arial, sans-serif;font-size:13px;line-height:1;text-align:left;color:#123456;', $html, 'c-all dari CML');
        $this->assertStringContainsString('Halo &amp; <b>dunia</b>', $html);
        $this->assertStringContainsString('href="https://x.test/?a=1&amp;b=2"', $html);
        $this->assertStringContainsString('class="c-column-per-50 c-outlook-group-fix"', $html);
        $this->assertStringContainsString('<td style="vertical-align:top;width:250px;">', $html);
    }

    public function testConsecutiveRendersDoNotLeakGlobalState() {
        $first = CEmail::builder()->createRuntimeBuilder();
        $first->addHead()->addHeadAttributes()->addAll()->setFontFamily('Georgia, serif');
        $first->setLang('id');
        $first->addBody()->setBackgroundColor('#111111')->addSection()->addColumn()->setWidth('30%')->addText()->add('x');
        $firstHtml = $first->render();
        $this->assertStringContainsString('font-family:Georgia, serif;', $firstHtml);

        $second = CEmail::builder()->createRuntimeBuilder();
        $second->addBody()->addSection()->addColumn()->addText()->add('y');
        $secondHtml = $second->render();

        $this->assertStringContainsString('font-family:Ubuntu, Helvetica, Arial, sans-serif;', $secondHtml, 'c-all render pertama tidak bocor');
        $this->assertStringNotContainsString('lang="id"', $secondHtml);
        $this->assertStringNotContainsString('#111111', $secondHtml);
        $this->assertStringNotContainsString('c-column-per-30', $secondHtml, 'media query render pertama tidak bocor');
        $this->assertSame($firstHtml, $first->render(), 'render ulang builder yang sama deterministik');
    }

    public function testSnapshotOfARepresentativeEmail() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $builder->addHead()->addHeadAttributes()->addAll()->setFontFamily('Arial, sans-serif');
        $body = $builder->addBody()->setBackgroundColor('#C4C4C4')->setPadding('30px 0px')->setWidth('650px');
        $header = $body->addSection()->setBackgroundColor('#ffffff')->setPadding('20px 0');
        $header->addColumn()->addImage()->setSrc('https://x.test/logo.png')->setWidth('128px')->setAlt('Logo');
        $header->addDivider()->setBorderColor('#1a347b');
        $content = $body->addSection()->setBackgroundColor('#ffffff')->setPadding('0 0 24px 0')->addColumn();
        $content->addText()->setFontSize('16px')->setLineHeight('25px')->setColor('#555555')->add('Hai Hery,');
        $content->addButton()->setBackgroundColor('#1a347b')->setHref('https://x.test/reset')->add('Reset Password');
        $footer = $body->addSection()->setBackgroundColor('#1a347b')->addColumn();
        $footer->addText()->setColor('#ffffff')->setAlign('center')->setFontSize('13px')->add('Copyright &copy; 2026 Cresenity');

        $fixture = __DIR__ . '/fixtures/builder-representative.html';
        $actual = $this->squash($builder->render());
        $this->assertFileExists($fixture, 'fixture snapshot belum dibuat');
        $this->assertSame($this->squash(file_get_contents($fixture)), $actual, 'snapshot email representatif berubah — perbarui fixture bila memang disengaja');
    }
}
