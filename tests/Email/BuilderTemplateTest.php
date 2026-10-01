<?php
use PHPUnit\Framework\TestCase;

/**
 * CEmail_Builder_Template: kerangka header/isi/footer, opsi, subclass, dan CEmail::template().
 */
class BuilderTemplateBrandedTemplate extends CEmail_Builder_Template {
    public function appName() {
        return 'Merek Uji';
    }

    public function primaryColor() {
        return '#aa0000';
    }

    public function footerText() {
        return 'Footer khusus';
    }
}

class BuilderTemplateNotATemplate {
}

class BuilderTemplateTest extends TestCase {
    /**
     * @param string $html
     *
     * @return string
     */
    protected function squash($html) {
        return trim(preg_replace('/\s+/', ' ', $html));
    }

    public function testDefaultTemplateRendersHeaderBodyAndFooter() {
        $template = CEmail::template(['app_name' => 'Toko <Satu>']);
        $template->bodySection()->addColumn()->addText()->add('Isi pesan');
        $html = $this->squash($template->render(['validationLevel' => 'strict']));

        $this->assertStringContainsString('Toko &lt;Satu&gt;', $html, 'nama app di-escape');
        $this->assertStringContainsString('Isi pesan', $html);
        $this->assertStringContainsString('Copyright &copy; ' . date('Y') . ' Toko &lt;Satu&gt;', $html);
        $this->assertStringContainsString('<title> Toko &lt;Satu&gt; </title>', $html);
        $this->assertLessThan(strpos($html, 'Isi pesan'), strpos($html, 'font-size:22px'), 'header sebelum isi');
        $this->assertGreaterThan(strpos($html, 'Isi pesan'), strpos($html, 'Copyright'), 'footer setelah isi');
        $this->assertStringContainsString('max-width:650px;', $html);
        $this->assertStringContainsString('background-color:#c4c4c4;', $html);
    }

    public function testLogoReplacesNameInHeader() {
        $html = CEmail::template(['app_name' => 'Toko', 'logo_url' => 'https://x.test/logo.png?a=1&b=2'])->render();

        $this->assertStringContainsString('src="https://x.test/logo.png?a=1&amp;b=2"', $html);
        $this->assertStringContainsString('alt="Toko"', $html);
        $this->assertStringNotContainsString('font-size:22px', $html, 'teks nama app diganti logo');
    }

    public function testHeaderAndFooterCanBeHidden() {
        $html = CEmail::template(['app_name' => 'Toko', 'show_header' => false, 'show_footer' => false])->render();

        $this->assertStringNotContainsString('font-size:22px', $html);
        $this->assertStringNotContainsString('Copyright', $html);
    }

    public function testOptionsOverrideColorsFontWidthAndFooter() {
        $template = CEmail::template([
            'app_name' => 'Toko',
            'primary_color' => '#112233',
            'text_color' => '#445566',
            'font_family' => 'Verdana, sans-serif',
            'width' => '500px',
            'footer_text' => '&copy; {year} {app_name} - Jakarta',
        ]);
        $template->bodySection()->addColumn()->addText()->add('isi');
        $html = $this->squash($template->render());

        $this->assertStringContainsString('max-width:500px;', $html);
        $this->assertStringContainsString('background:#112233;', $html, 'footer memakai warna utama');
        $this->assertStringContainsString('font-family:Verdana, sans-serif;', $html);
        $this->assertStringContainsString('color:#445566;">isi', $html, 'warna teks default lewat default atribut c-text');
        $this->assertStringContainsString('&copy; ' . date('Y') . ' Toko - Jakarta', $html);
    }

    public function testTitleAndPreview() {
        $template = CEmail::template(['app_name' => 'Toko'])->title('Reset sandi')->preview('Klik untuk mengatur ulang');
        $html = $this->squash($template->render());

        $this->assertStringContainsString('<title> Reset sandi </title>', $html);
        $this->assertStringContainsString('Klik untuk mengatur ulang', $html);
    }

    public function testSubclassOverridesAreUsed() {
        $template = new BuilderTemplateBrandedTemplate();
        $html = $this->squash($template->render());

        $this->assertStringContainsString('Merek Uji', $html);
        $this->assertStringContainsString('Footer khusus', $html);
        $this->assertStringContainsString('background:#aa0000;', $html);
    }

    public function testFactoryResolvesClassFromOption() {
        $this->assertInstanceOf(CEmail_Builder_Template_DefaultTemplate::class, CEmail::template());
        $this->assertInstanceOf(BuilderTemplateBrandedTemplate::class, CEmail::template(['class' => BuilderTemplateBrandedTemplate::class]));
    }

    public function testFactoryRejectsNonTemplateClass() {
        $this->expectException(CEmail_Builder_Exception::class);
        CEmail::template(['class' => BuilderTemplateNotATemplate::class]);
    }

    public function testBuildResetsContentAndBodySectionIsLazy() {
        $template = CEmail::template(['app_name' => 'Toko']);
        $template->bodySection()->addColumn()->addText()->add('lama');
        $template->build();
        $template->bodySection()->addColumn()->addText()->add('baru');
        $html = $template->render();

        $this->assertStringNotContainsString('lama', $html);
        $this->assertStringContainsString('baru', $html);
    }

    public function testMethodAbstractEmailTemplateHelper() {
        $method = new class() extends CNotification_MethodAbstract {
            public function execute() {
                return [];
            }
        };
        $template = $method->emailTemplate(['app_name' => 'Toko']);

        $this->assertInstanceOf(CEmail_Builder_Template::class, $template);
        $this->assertSame('Toko', $template->appName());
    }
}
