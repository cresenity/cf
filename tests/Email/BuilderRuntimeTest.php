<?php
use PHPUnit\Framework\TestCase;

/**
 * Komponen dan helper builder yang menyusul: title/preview/font/breakpoint, spacer, wrapper, c-all/c-class,
 * validator atribut, opsi fonts, head style komponen, dan isolasi render bersarang.
 */
class BuilderRuntimeProbeHeadStyle extends CEmail_Builder_Component_BodyComponent {
    protected static $tagName = 'c-builder-runtime-probe-head-style';

    protected $componentHeadStyle = ['.probe-head-style { color:#123456; }'];

    public function render() {
        return '<div class="probe-head-style">probe</div>';
    }
}

class BuilderRuntimeProbeNested extends CEmail_Builder_Component_BodyComponent {
    protected static $tagName = 'c-builder-runtime-probe-nested';

    public function render() {
        $inner = CEmail::builder()->createRuntimeBuilder();
        $inner->addHead()->addTitle('inner title');
        $inner->addBody()->addSection()->addColumn()->setWidth('25%')->addText()->add('inner');
        $inner->render();

        return '<div>nested</div>';
    }
}

class BuilderRuntimeTest extends TestCase {
    /**
     * @param string $html
     *
     * @return string
     */
    protected function squash($html) {
        return trim(preg_replace('/\s+/', ' ', $html));
    }

    /**
     * @return CEmail_Builder_RuntimeBuilder
     */
    protected function newBuilder() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $builder->addBody()->addSection()->addColumn()->addText()->add('isi');

        return $builder;
    }

    public function testHeadHelpersTitlePreviewFontBreakpointStyle() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $head = $builder->addHead();
        $head->addTitle('Judul & "uji"');
        $head->addPreview('Pratinjau email');
        $head->addFont('Poppins', 'https://fonts.test/poppins.css');
        $head->addBreakpoint('320px');
        $head->addStyle('.x{color:red}');
        $builder->addBody()->addSection()->addColumn()->addText()->setFontFamily('Poppins, Arial')->add('isi');
        $html = $this->squash($builder->render());

        $this->assertStringContainsString('<title> Judul &amp; &quot;uji&quot; </title>', $html, 'judul di-escape tanpa dobel-encode');
        $this->assertStringContainsString('Pratinjau email', $html);
        $this->assertStringContainsString('display:none;font-size:1px;', $html, 'preheader tersembunyi');
        $this->assertStringContainsString('<link href="https://fonts.test/poppins.css" rel="stylesheet" type="text/css">', $html);
        $this->assertStringContainsString('@media only screen and (min-width:320px)', $html);
        $this->assertStringContainsString('.x{color:red}', $html);
    }

    public function testFontLinkOnlyWhenFontIsUsed() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $builder->addHead()->addFont('Poppins', 'https://fonts.test/poppins.css');
        $builder->addBody()->addSection()->addColumn()->addText()->add('isi');
        $this->assertStringNotContainsString('fonts.test', $builder->render());
    }

    public function testRenderOptionFontsEmptyDropsGoogleLinks() {
        $builder = $this->newBuilder();
        $this->assertStringContainsString('fonts.googleapis.com/css?family=Ubuntu', $builder->render(), 'bawaan tetap memuat Ubuntu');

        $builder = $this->newBuilder();
        $this->assertStringNotContainsString('fonts.googleapis.com', $builder->render(['fonts' => []]));
    }

    public function testSpacerRendersHeightAndLineHeight() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $column = $builder->addBody()->addSection()->addColumn();
        $column->addSpacer();
        $column->addSpacer()->setHeight('48px');
        $html = $this->squash($builder->render());

        $this->assertStringContainsString('<div style="height:20px;line-height:20px;">&nbsp;</div>', $html, 'tinggi bawaan 20px');
        $this->assertStringContainsString('<div style="height:48px;line-height:48px;">&nbsp;</div>', $html);
    }

    public function testWrapperWrapsSectionsInOutlookRows() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $wrapper = $builder->addBody()->addWrapper()->setBackgroundColor('#eeeeee');
        $wrapper->addSection()->addColumn()->addText()->add('satu');
        $wrapper->addSection()->addColumn()->addText()->add('dua');
        $html = $this->squash($builder->render());

        $this->assertStringContainsString('<div style="background:#eeeeee;background-color:#eeeeee;margin:0px auto;max-width:600px;">', $html);
        $this->assertSame(2, substr_count($html, '<td width="600">'), 'tiap section dibungkus tr/td mso selebar kontainer');
        $this->assertStringContainsString('satu', $html);
        $this->assertStringContainsString('dua', $html);
    }

    public function testWrapperFullWidth() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $wrapper = $builder->addBody()->addWrapper()->setFullWidth('full-width')->setBackgroundColor('#eeeeee');
        $wrapper->addSection()->addColumn()->addText()->add('isi');
        $html = $this->squash($builder->render());

        $this->assertStringContainsString('<table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="background:#eeeeee;background-color:#eeeeee;width:100%;">', $html);
    }

    public function testAddAllAndNamedClasses() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $attributes = $builder->addHead()->addHeadAttributes();
        $attributes->addAll()->setFontFamily('Verdana, sans-serif');
        $attributes->addClass('lead')->setColor('#ff0000')->setFontSize('20px');
        $column = $builder->addBody()->addSection()->addColumn();
        $column->addText()->add('biasa');
        $column->addText()->useClass('lead')->add('menonjol');
        $column->addText()->useClass('lead')->setColor('#00ff00')->add('timpa');
        $html = $this->squash($builder->render());

        $this->assertStringContainsString('font-family:Verdana, sans-serif;font-size:13px;line-height:1;text-align:left;color:#000000;">biasa', $html);
        $this->assertStringContainsString('font-family:Verdana, sans-serif;font-size:20px;line-height:1;text-align:left;color:#ff0000;">menonjol', $html);
        $this->assertStringContainsString('font-size:20px;line-height:1;text-align:left;color:#00ff00;">timpa', $html, 'atribut node menang atas kelas');
    }

    public function testRawAndSocialHelpersReachable() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $column = $builder->addBody()->addSection()->addColumn();
        $column->addRaw()->add('<p class="mentah">x</p>');
        $social = $column->addSocial();
        $social->addSocialElement()->setName('facebook')->setHref('https://fb.test/x');
        $html = $builder->render();

        $this->assertStringContainsString('<p class="mentah">x</p>', $html);
        $this->assertStringContainsString('https://fb.test/x', $html);
    }

    public function testValidatorReportsUnknownAttributes() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $builder->addBody()->setColor('#555555')->addSection()->addColumn()->addText()->setColor('#111111')->add('x');
        $node = new ReflectionProperty($builder, 'node');
        $node->setAccessible(true);

        $validator = new CEmail_Builder_Validator($node->getValue($builder));
        $errors = $validator->validate();

        $this->assertCount(1, $errors, 'hanya color di body yang tidak dikenal');
        $this->assertStringContainsString('"color"', $errors[0]);
        $this->assertStringContainsString('<c-body>', $errors[0]);
    }

    public function testValidationLevels() {
        $make = function () {
            $builder = CEmail::builder()->createRuntimeBuilder();
            $builder->addBody()->setFontFamily('Arial')->addSection()->addColumn()->addText()->add('x');

            return $builder;
        };

        $this->assertStringContainsString('x', $make()->render(), 'soft tidak mengubah hasil render');
        $this->assertStringContainsString('x', $make()->render(['validationLevel' => 'skip']));

        $this->expectException(CEmail_Builder_Exception::class);
        $this->expectExceptionMessage('font-family');
        $make()->render(['validationLevel' => 'strict']);
    }

    public function testComponentHeadStyleIsRendered() {
        CEmail::builder()->registerComponent(BuilderRuntimeProbeHeadStyle::class);
        $builder = CEmail::builder()->createRuntimeBuilder();
        $builder->addBody()->addNode('c-builder-runtime-probe-head-style');
        $html = $builder->render();

        $this->assertStringContainsString('.probe-head-style { color:#123456; }', $html);
        $this->assertStringContainsString('<div class="probe-head-style">probe</div>', $html);
    }

    public function testNestedRenderDoesNotOverwriteOuterState() {
        CEmail::builder()->registerComponent(BuilderRuntimeProbeNested::class);
        $builder = CEmail::builder()->createRuntimeBuilder();
        $builder->addHead()->addTitle('outer title');
        $section = $builder->addBody()->addSection();
        $section->addColumn()->setWidth('50%')->addText()->add('luar');
        $section->addNode('c-builder-runtime-probe-nested');
        $html = $this->squash($builder->render());

        $this->assertStringContainsString('<title> outer title </title>', $html);
        $this->assertStringNotContainsString('inner title', $html);
        $this->assertStringContainsString('.c-column-per-50', $html, 'media query render luar tetap ada');
        $this->assertStringNotContainsString('.c-column-per-25', $html, 'media query render dalam tidak bocor');
    }

    public function testGlobalDataAccessorFollowsActiveRender() {
        $outside = CEmail::builder()->globalData();
        $this->assertSame($outside, CEmail::builder()->globalData());

        $this->newBuilder()->render();

        $this->assertSame($outside, CEmail::builder()->globalData(), 'setelah render kembali ke instance bawaan');
    }
}
