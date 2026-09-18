<?php

use PHPUnit\Framework\TestCase;

/**
 * CJavascript: statement builder (jQuery/variable/function/if/raw) → string JS, registry statement
 * global vs deferred stack + compile(), prepValue/escaping; CStringBuilder indentasi; CParser_Sql
 * format/highlight; CParser_CssToInlineStyles.
 */
class JavascriptAndStringBuilderTest extends TestCase {
    protected function setUp(): void {
        CJavascript::clearStatement();
        CJavascript::clearDeferredStack();
    }

    protected function tearDown(): void {
        CJavascript::clearStatement();
        CJavascript::clearDeferredStack();
    }

    public function testJqueryStatementChainsMethodsAndQuotesStringArguments() {
        $js = CJavascript::jqueryStatement('#tombol')->addClass('aktif')->attr('data-x', 'nilai "kutip"')->hide()->getStatement();

        $this->assertSame('$("#tombol").addClass("aktif").attr("data-x","nilai \\"kutip\\"").hide();', $js);
    }

    public function testJqueryStatementLeavesCodeLikeArgumentsUnquoted() {
        $js = CJavascript::jqueryStatement('this')->val('event.target.value')->getStatement();
        $this->assertSame('$("this").val("event.target.value");', $js, 'argumen string metode jQuery selalu dikutip (heuristik containsCode hanya untuk prepValue)');

        $nested = CJavascript::jqueryStatement('#a')->html(CJavascript::jqueryStatement('#b')->val())->getStatement();
        $this->assertSame('$("#a").html($("#b").val());', $nested, 'statement sebagai argumen disisipkan tanpa kutip dan tanpa ; akhir');

        $array = CJavascript::jqueryStatement('#a')->css(['color', 'red'])->getStatement();
        $this->assertSame('$("#a").css(color,red);', $array, 'array digabung koma apa adanya');
    }

    public function testJqueryAjaxSwitchesToTheBareDollar() {
        $js = CJavascript::jqueryStatement('#a')->ajax(['url' => '/x'])->getStatement();

        $this->assertStringStartsWith('$.ajax(', $js);
    }

    public function testVariableFunctionIfAndRawStatements() {
        $this->assertSame('var nama = "Budi";', CJavascript::variableStatement('nama', 'Budi')->getStatement());
        $this->assertSame('var el = this.value;', CJavascript::variableStatement('el', 'this.value')->getStatement(), 'nilai berkode tidak dikutip');
        $this->assertSame('var daftar = "a,b";', CJavascript::variableStatement('daftar', ['a', 'b'])->getStatement());

        $function = CJavascript::functionStatement('sapa', ['nama']);
        $function->addStatement(CJavascript::variableStatement('x', 1));
        $function->addStatement('return x;');
        $this->assertSame('function sapa(nama) {var x = "1";return x;}', $function->getStatement());

        $if = CJavascript::ifStatement(CJavascript::jqueryStatement('#a')->val(), '==', '"x"');
        $if->addStatement('alert(1);');
        $this->assertSame('if ($("#a").val() == "x") {alert(1);}', $if->getStatement());

        $this->assertSame('console.log(1);', CJavascript::createRawStatement('console.log(1);')->getStatement());
    }

    public function testPrepValueEscapesAndUnwrapsPercentMarkers() {
        $this->assertSame('"a\\\\b"', CJavascript_Helper_Javascript::prepValue('a\\b'));
        $this->assertSame('"say \\"hi\\""', CJavascript_Helper_Javascript::prepValue('say "hi"'));
        $this->assertSame('this.x', CJavascript_Helper_Javascript::prepValue('this.x'));
        $this->assertSame('"a,b"', CJavascript_Helper_Javascript::prepValue(['a', 'b']));
        $this->assertSame('$("#x")', CJavascript_Helper_Javascript::prepJQuerySelector('#x'));
        $this->assertSame('$("#x")', CJavascript_Helper_Javascript::prepJQuerySelector('$("#x")'), 'sudah selector jQuery dibiarkan');
    }

    public function testGlobalStatementsCompileDeduplicatedByHash() {
        $a = CJavascript::createRawStatement('a();');
        CJavascript::addStatement($a);
        CJavascript::addStatement($a);
        CJavascript::addStatement(CJavascript::createRawStatement('b();'));
        CJavascript::addRaw('c();');

        $this->assertSame('a();b();c();', CJavascript::compile());
        $this->assertCount(3, CJavascript::getStatements());

        CJavascript::removeStatement($a);
        $this->assertSame('b();c();', CJavascript::compile());
        CJavascript::clearStatement();
        $this->assertSame('', CJavascript::compile());
    }

    public function testDeferredStackCapturesStatementsAddedInsideAClosure() {
        CJavascript::addRaw('global();');

        $captured = CJavascript::getJsStatementFromClosure(function ($nama) {
            CJavascript::addRaw('dalam("' . $nama . '");');
            CJavascript::addStatement(CJavascript::jqueryStatement('#x')->hide());
            CJavascript::jqueryStatement('#y')->show();
        }, ['budi']);

        $this->assertIsArray($captured);
        $captured = array_values($captured);
        $this->assertCount(2, $captured, 'statement yang hanya dibuat (tanpa addStatement) tidak ikut');
        $this->assertSame('dalam("budi");', $captured[0]->getStatement(), 'statement di dalam closure masuk ke stack deferred (dikunci hash), bukan global');
        $this->assertSame('$("#x").hide();', $captured[1]->getStatement());
        $this->assertSame('global();', CJavascript::compile(), 'kompilasi global tidak tersentuh');
        $this->assertSame([], CJavascript::getDeferredStack(), 'stack deferred kembali kosong sesudah closure');
    }

    public function testStringBuilderIndentsPerLine() {
        $builder = CStringBuilder::factory()->setIndent(1);
        $builder->appendln('a')->br()->incIndent()->appendln('b')->br()->decIndent(2)->appendln('c');

        $this->assertSame("\ta\r\n\t\tb\r\nc", $builder->text());
        $this->assertSame((string) $builder, $builder->text());
        $this->assertSame(0, $builder->getIndent(), 'decIndent di bawah nol tetap 0');
        $this->assertSame("\t\t", CStringBuilder::indent(2));
        $this->assertSame('    ', CStringBuilder::indent(2, '  '));
        $this->assertSame('x', (new CStringBuilder('x'))->text());
    }

    public function testSqlFormatterFormatsAndHighlights() {
        $formatted = CParser_Sql::format('select a, b from t where a = 1 and b in (1,2) order by a');

        $this->assertStringContainsString("\nfrom \n", $formatted, 'klausa utama dipecah per baris (huruf tidak diubah)');
        $this->assertStringContainsString("\nwhere \n", $formatted);
        $this->assertStringContainsString("\norder by \n", $formatted);
        $this->assertStringContainsString('in (1, 2)', $formatted);
        $highlighted = CParser_Sql::highlight('select 1');
        $this->assertStringContainsString('select', $highlighted);
        $this->assertTrue(strpos($highlighted, '<span') !== false || strpos($highlighted, "\033[") !== false, 'HTML di web, kode warna ANSI di CLI');
    }

    public function testCssToInlineStylesAppliesStylesheetRules() {
        $converter = CParser::cssToInlineStyles();
        $html = '<html><body><p class="merah">x</p><p>y</p></body></html>';

        $result = $converter->convert($html, 'p { margin: 0 } .merah { color: red }');

        $this->assertStringContainsString('<p class="merah" style="margin: 0; color: red;">x</p>', $result);
        $this->assertStringContainsString('<p style="margin: 0;">y</p>', $result);
    }
}
