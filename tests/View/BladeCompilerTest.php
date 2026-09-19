<?php
use PHPUnit\Framework\TestCase;

/**
 * Top-level CView_Compiler_BladeCompiler::compileString() pipeline behavior
 * not owned by any single compile*Trait: verbatim/@php block extraction,
 * custom directives, extensions, precompilers, and the withDoubleEncoding/
 * withoutComponentTags/component-alias registration API.
 */
class BladeCompilerTest extends TestCase {
    protected function compiler() {
        return new CView_Compiler_BladeCompiler();
    }

    public function testVerbatimBlockIsLeftUncompiled() {
        $result = $this->compiler()->compileString('@verbatim {{ $name }} @endverbatim');

        $this->assertSame(' {{ $name }} ', $result);
    }

    public function testMultipleVerbatimBlocksAreEachRestored() {
        $result = $this->compiler()->compileString('@verbatim{{ $a }}@endverbatim mid @verbatim{{ $b }}@endverbatim');

        $this->assertSame('{{ $a }} mid {{ $b }}', $result);
    }

    public function testCustomDirectiveIsInvokedWithItsExpression() {
        $compiler = $this->compiler();
        $compiler->directive('datetime', function ($expression) {
            return "<?php echo ({$expression})->format('Y-m-d'); ?>";
        });

        $this->assertSame(
            "<?php echo (\$date)->format('Y-m-d'); ?>",
            $compiler->compileString('@datetime($date)')
        );
    }

    public function testDirectiveRejectsInvalidNames() {
        $this->expectException(InvalidArgumentException::class);

        $this->compiler()->directive('invalid-name!', function () {
            return '';
        });
    }

    public function testGetCustomDirectivesReturnsRegisteredDirectives() {
        $compiler = $this->compiler();
        $handler = function () {
            return '';
        };
        $compiler->directive('foo', $handler);

        $this->assertSame(['foo' => $handler], $compiler->getCustomDirectives());
    }

    public function testExtendRunsRegisteredExtensionsOverTheWholeValue() {
        $compiler = $this->compiler();
        $compiler->extend(function ($value) {
            return str_replace('FOO', 'BAR', $value);
        });

        $this->assertSame('BAR', $compiler->compileString('FOO'));
        $this->assertCount(1, $compiler->getExtensions());
    }

    public function testPrecompilerRunsBeforeStatementCompilation() {
        $compiler = $this->compiler();
        $compiler->precompiler(function ($value) {
            return str_replace('@custompre', '@if(true)', $value);
        });

        $this->assertSame('<?php if(true): ?>', $compiler->compileString('@custompre'));
    }

    public function testAliasIfRegistersIfUnlessElseAndEndDirectives() {
        $compiler = $this->compiler();
        $compiler->aliasIf('disk', function ($value = null) {
            return $value === 'local';
        });

        $this->assertSame(
            "<?php if (CView_Blade::check('disk', 'local')): ?>",
            $compiler->compileString("@disk('local')")
        );
        $this->assertSame(
            "<?php if (! CView_Blade::check('disk', 'local')): ?>",
            $compiler->compileString("@unlessdisk('local')")
        );
        // Note the source's "else" branch prefixes a literal backslash
        // (\CView_Blade::check) unlike the "if"/"unless" branches - harmless
        // since there's no namespace here, but asserted verbatim below.
        $this->assertSame(
            "<?php elseif (\CView_Blade::check('disk', 'local')): ?>",
            $compiler->compileString("@elsedisk('local')")
        );
        $this->assertSame('<?php endif; ?>', $compiler->compileString('@enddisk'));
    }

    public function testCheckDelegatesToTheRegisteredCondition() {
        $compiler = $this->compiler();
        $compiler->aliasIf('disk', function ($value) {
            return $value === 'local';
        });

        $this->assertTrue($compiler->check('disk', 'local'));
        $this->assertFalse($compiler->check('disk', 's3'));
    }

    public function testStripParenthesesRemovesOuterParensOnly() {
        $compiler = $this->compiler();

        $this->assertSame('$foo', $compiler->stripParentheses('($foo)'));
        $this->assertSame('$foo', $compiler->stripParentheses('$foo'));
    }

    public function testWithoutComponentTagsDisablesHtmlComponentTagCompilation() {
        $compiler = $this->compiler();
        $compiler->withoutComponentTags();

        $this->assertSame(
            '<x-alert type="error" />',
            $compiler->compileString('<x-alert type="error" />')
        );
    }

    public function testComponentRegistersAKebabCaseAliasByDefault() {
        $compiler = $this->compiler();
        $compiler->component('App\View\Components\AlertBox');

        $this->assertSame(
            ['alert-box' => 'App\View\Components\AlertBox'],
            $compiler->getClassComponentAliases()
        );
    }

    public function testComponentAcceptsAnExplicitAlias() {
        $compiler = $this->compiler();
        $compiler->component('App\View\Components\AlertBox', 'alert');

        $this->assertSame(
            ['alert' => 'App\View\Components\AlertBox'],
            $compiler->getClassComponentAliases()
        );
    }

    public function testComponentsRegistersMultipleAliasesAtOnce() {
        $compiler = $this->compiler();
        $compiler->components([
            'App\View\Components\Alert' => 'alert',
            'App\View\Components\Badge',
        ]);

        $this->assertSame(
            [
                'alert' => 'App\View\Components\Alert',
                'badge' => 'App\View\Components\Badge',
            ],
            $compiler->getClassComponentAliases()
        );
    }

    public function testComponentNamespaceRegistersAnAnonymousClassNamespace() {
        $compiler = $this->compiler();
        $compiler->componentNamespace('App\View\Components\Forms', 'forms');

        $this->assertSame(
            ['forms' => 'App\View\Components\Forms'],
            $compiler->getClassComponentNamespaces()
        );
    }

    public function testAliasComponentRegistersStartAndEndDirectives() {
        $compiler = $this->compiler();
        $compiler->aliasComponent('components.alert', 'alert');

        $this->assertSame(
            "<?php \$__env->startComponent('components.alert', ['type' => 'error']); ?>",
            $compiler->compileString("@alert(['type' => 'error'])")
        );
        $this->assertSame(
            '<?php echo $__env->renderComponent(); ?>',
            $compiler->compileString('@endalert')
        );
    }

    public function testAliasIncludeRegistersAnIncludeShortcutDirective() {
        $compiler = $this->compiler();
        $compiler->aliasInclude('partials.alert', 'alert');

        $result = $compiler->compileString("@alert(['type' => 'error'])");

        $this->assertStringContainsString("\$__env->make('partials.alert', ['type' => 'error']", $result);
    }

    public function testAliasIncludeCompilesToCarrExcept() {
        $compiler = $this->compiler();
        $compiler->aliasInclude('partials.card', 'card');
        $result = $compiler->compileString("@card(['title' => \$t])");

        $this->assertStringContainsString("\$__env->make('partials.card', ['title' => \$t], \\carr::except(get_defined_vars(), ['__data', '__path']))->render()", $result);
        $this->assertStringNotContainsString('Illuminate', $result, 'hasil kompilasi tidak boleh merujuk kelas yang tidak ada');
    }
}
