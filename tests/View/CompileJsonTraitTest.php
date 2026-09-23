<?php
use PHPUnit\Framework\TestCase;

class CompileJsonTraitTest extends TestCase {
    protected function compiler() {
        return new CView_Compiler_BladeCompiler();
    }

    public function testJsonDirectiveUsesDefaultOptionsAndDepth() {
        $this->assertSame(
            '<?php echo json_encode($array, 15, 512) ?>',
            $this->compiler()->compileString('@json($array)')
        );
    }

    public function testJsonDirectiveWithCustomOptions() {
        $this->assertSame(
            '<?php echo json_encode($array, JSON_PRETTY_PRINT, 512) ?>',
            $this->compiler()->compileString('@json($array, JSON_PRETTY_PRINT)')
        );
    }

    public function testJsonDirectiveWithCustomOptionsAndDepth() {
        $this->assertSame(
            '<?php echo json_encode($array, JSON_PRETTY_PRINT, 3) ?>',
            $this->compiler()->compileString('@json($array, JSON_PRETTY_PRINT, 3)')
        );
    }

    public function testJsonAttrDirectiveEscapesForHtmlAttributes() {
        $this->assertSame(
            "<?php echo htmlspecialchars(json_encode(\$array, 15, 512), ENT_QUOTES, 'UTF-8') ?>",
            $this->compiler()->compileString('@jsonAttr($array)')
        );
    }

    public function testJsonDirectiveWithArrayLiteralOfMoreThanTwoEntries() {
        // A plain explode(',', ...) on the stripped expression sees 3 commas
        // here (one per entry) and mis-splits into 4 "arguments", silently
        // dropping the 4th ('c' => 3) since compileJson only ever reads
        // parts[0..2].
        $this->assertSame(
            "<?php echo json_encode(['a' => 1, 'b' => 2, 'c' => 3], 15, 512) ?>",
            $this->compiler()->compileString("@json(['a' => 1, 'b' => 2, 'c' => 3])")
        );
    }

    public function testJsonDirectiveWithValueContainingAnInternalComma() {
        // c::env()'s own comma-separated arguments must not be mistaken for
        // the @json directive's own (value, options, depth) separators.
        $this->assertSame(
            "<?php echo json_encode(['url' => c::env('KEY', 'default')], 15, 512) ?>",
            $this->compiler()->compileString("@json(['url' => c::env('KEY', 'default')])")
        );
    }

    public function testJsonDirectiveWithMultilineArrayAndNestedTernary() {
        // Regression for the exact shape that broke application/spedo's
        // app-spa.blade.php: a multi-item array whose value is itself a
        // multi-line expression containing its own comma and parentheses.
        $expression = <<<'BLADE'
@json([
    'a' => CF::orgCode(),
    'b' => c::env('KEY', CF::isProduction()
        ? 'prod-value'
        : 'dev-value'),
    'c' => false,
])
BLADE;

        $compiled = $this->compiler()->compileString($expression);

        $this->assertStringContainsString("'c' => false,", $compiled);
        $this->assertStringEndsWith('], 15, 512) ?>', trim($compiled));
    }
}
