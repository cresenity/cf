<?php

/**
 * Description of BladeCompiler.
 */
class CView_Compiler_BladeCompiler extends CView_CompilerAbstract implements CView_CompilerInterface {
    use CView_Compiler_BladeCompiler_CompileAuthorizationTrait,
        CView_Compiler_BladeCompiler_CompileClassesTrait,
        CView_Compiler_BladeCompiler_CompileCommentTrait,
        CView_Compiler_BladeCompiler_CompileComponentTrait,
        CView_Compiler_BladeCompiler_CompileConditionalTrait,
        CView_Compiler_BladeCompiler_CompileEchoTrait,
        CView_Compiler_BladeCompiler_CompileFragmentTrait,
        CView_Compiler_BladeCompiler_CompileErrorTrait,
        CView_Compiler_BladeCompiler_CompileHelperTrait,
        CView_Compiler_BladeCompiler_CompileIncludeTrait,
        CView_Compiler_BladeCompiler_CompileInjectionTrait,
        CView_Compiler_BladeCompiler_CompileJsonTrait,
        CView_Compiler_BladeCompiler_CompileJsTrait,
        CView_Compiler_BladeCompiler_CompileLayoutTrait,
        CView_Compiler_BladeCompiler_CompileLoopTrait,
        CView_Compiler_BladeCompiler_CompileRawPhpTrait,
        CView_Compiler_BladeCompiler_CompileSessionTrait,
        CView_Compiler_BladeCompiler_CompileStackTrait,
        CView_Compiler_BladeCompiler_CompileStyleTrait,
        CView_Compiler_BladeCompiler_CompileTranslationTrait,
        CView_Compiler_BladeCompiler_CompileUseStatementTrait,
        CTrait_ReflectsClosureTrait;

    /**
     * All of the registered extensions.
     *
     * @var array
     */
    protected $extensions = [];

    /**
     * All custom "directive" handlers.
     *
     * @var array
     */
    protected $customDirectives = [];

    /**
     * All custom "condition" handlers.
     *
     * @var array
     */
    protected $conditions = [];

    /**
     * The registered string preparation callbacks.
     *
     * @var array
     */
    protected $prepareStringsForCompilationUsing = [];

    /**
     * All of the registered precompilers.
     *
     * @var array
     */
    protected $precompilers = [];

    /**
     * The file currently being compiled.
     *
     * @var string
     */
    protected $path;

    /**
     * All of the available compiler functions.
     *
     * @var string[]
     */
    protected $compilers = [
        // 'Comments',
        'Extensions',
        'Statements',
        'Echos',
    ];

    /**
     * Array of opening and closing tags for raw echos.
     *
     * @var string[]
     */
    protected $rawTags = ['{!!', '!!}'];

    /**
     * Array of opening and closing tags for regular echos.
     *
     * @var string[]
     */
    protected $contentTags = ['{{', '}}'];

    /**
     * Array of opening and closing tags for escaped echos.
     *
     * @var string[]
     */
    protected $escapedTags = ['{{{', '}}}'];

    /**
     * The "regular" / legacy echo string format.
     *
     * @var string
     */
    protected $echoFormat = 'c::e(%s)';

    /**
     * Array of footer lines to be added to template.
     *
     * @var array
     */
    protected $footer = [];

    /**
     * Array to temporary store the raw blocks found in the template.
     *
     * @var array
     */
    protected $rawBlocks = [];

    /**
     * The array of anonymous component paths to search for components in.
     *
     * @var array
     */
    protected $anonymousComponentPaths = [];

    /**
     * The array of anonymous component namespaces to autoload from.
     *
     * @var array
     */
    protected $anonymousComponentNamespaces = [];

    /**
     * The array of class component aliases and their class names.
     *
     * @var array
     */
    protected $classComponentAliases = [];

    /**
     * The array of class component namespaces to autoload from.
     *
     * @var array
     */
    protected $classComponentNamespaces = [];

    /**
     * Indicates if component tags should be compiled.
     *
     * @var bool
     */
    protected $compilesComponentTags = true;

    /**
     * @var CView_Compiler_BladeCompiler
     */
    private static $instance;

    /**
     * @return CView_Compiler_BladeCompiler
     */
    public static function instance() {
        if (static::$instance == null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Compile the view at the given path.
     *
     * @param null|string $path
     *
     * @return void
     */
    public function compile($path = null) {
        if ($path) {
            $this->setPath($path);
        }

        if (!is_null($this->cachePath)) {
            $contents = $this->compileString(CFile::get($this->getPath()));

            if (!empty($this->getPath())) {
                $contents = $this->appendFilePath($contents);
            }
            $this->ensureCompiledDirectoryExists(
                $compiledPath = $this->getCompiledPath($this->getPath())
            );
            CFile::put($compiledPath, $contents);
        }
    }

    /**
     * Append the file path to the compiled string.
     *
     * @param string $contents
     *
     * @return string
     */
    protected function appendFilePath($contents) {
        $tokens = $this->getOpenAndClosingPhpTokens($contents);

        if ($tokens->isNotEmpty() && $tokens->last() !== T_CLOSE_TAG) {
            $contents .= ' ?>';
        }

        return $contents . "<?php /**PATH {$this->getPath()} ENDPATH**/ ?>";
    }

    /**
     * Get the open and closing PHP tag tokens from the given string.
     *
     * @param string $contents
     *
     * @return CCollection
     */
    protected function getOpenAndClosingPhpTokens($contents) {
        return c::collect(token_get_all($contents))
            ->pluck(0)
            ->filter(function ($token) {
                return in_array($token, [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG]);
            });
    }

    /**
     * Get the path currently being compiled.
     *
     * @return string
     */
    public function getPath() {
        return $this->path;
    }

    /**
     * Set the path currently being compiled.
     *
     * @param string $path
     *
     * @return void
     */
    public function setPath($path) {
        $this->path = $path;
    }

    /**
     * Compile the given Blade template contents.
     *
     * @param string $value
     *
     * @return string
     */
    public function compileString($value) {
        list($this->footer, $result) = [[], ''];

        foreach ($this->prepareStringsForCompilationUsing as $callback) {
            $value = $callback($value);
        }
        $value = $this->storeUncompiledBlocks($value);

        // First we will compile the Blade component tags. This is a precompile style
        // step which compiles the component Blade tags into @component directives
        // that may be used by Blade. Then we should call any other precompilers.
        $value = $this->compileComponentTags(
            $this->compileComments($value)
        );

        foreach ($this->precompilers as $precompiler) {
            $value = call_user_func($precompiler, $value);
        }

        // Here we will loop through all of the tokens returned by the Zend lexer and
        // parse each one into the corresponding valid PHP. We will then have this
        // template as the correctly rendered PHP that can be rendered natively.
        foreach (token_get_all($value) as $token) {
            $result .= is_array($token) ? $this->parseToken($token) : $token;
        }

        if (!empty($this->rawBlocks)) {
            $result = $this->restoreRawContent($result);
        }

        // If there are any footer lines that need to get added to a template we will
        // add them here at the end of the template. This gets used mainly for the
        // template inheritance via the extends keyword that should be appended.
        if (count($this->footer) > 0) {
            $result = $this->addFooters($result);
        }

        if (!empty($this->echoHandlers)) {
            $result = $this->addBladeCompilerVariable($result);
        }

        return str_replace(
            ['##BEGIN-COMPONENT-CLASS##', '##END-COMPONENT-CLASS##'],
            '',
            $result
        );
    }

    /**
     * Evaluate and render a Blade string to HTML.
     *
     * @param string $string
     * @param array  $data
     * @param bool   $deleteCachedView
     *
     * @return string
     */
    public static function render($string, $data = [], $deleteCachedView = false) {
        $component = new CView_Component_TemplateComponent($string);

        $view = CView::factory()->make($component->resolveView(), $data);

        return c::tap($view->render(), function () use ($view, $deleteCachedView) {
            if ($deleteCachedView) {
                @unlink($view->getPath());
            }
        });
    }

    /**
     * Render a component instance to HTML.
     *
     * @param \CView_ComponentAbstract $component
     *
     * @return string
     */
    public static function renderComponent(CView_ComponentAbstract $component) {
        $data = $component->data();

        $view = c::value($component->resolveView(), $data);

        if ($view instanceof CView_View) {
            return $view->with($data)->render();
        } elseif ($view instanceof CInterface_Htmlable) {
            return $view->toHtml();
        } else {
            return CView::factory()->make($view, $data)
                ->render();
        }
    }

    /**
     * Store the blocks that do not receive compilation.
     *
     * @param string $value
     *
     * @return string
     */
    protected function storeUncompiledBlocks($value) {
        if (strpos($value, '@verbatim') !== false) {
            $value = $this->storeVerbatimBlocks($value);
        }

        if (strpos($value, '@php') !== false) {
            $value = $this->storePhpBlocks($value);
        }

        return $value;
    }

    /**
     * Store the verbatim blocks and replace them with a temporary placeholder.
     *
     * @param string $value
     *
     * @return string
     */
    protected function storeVerbatimBlocks($value) {
        return preg_replace_callback('/(?<!@)@verbatim(.*?)@endverbatim/s', function ($matches) {
            return $this->storeRawBlock($matches[1]);
        }, $value);
    }

    /**
     * Store the PHP blocks and replace them with a temporary placeholder.
     *
     * @param string $value
     *
     * @return string
     */
    protected function storePhpBlocks($value) {
        return preg_replace_callback('/(?<!@)@php(.*?)@endphp/s', function ($matches) {
            return $this->storeRawBlock("<?php{$matches[1]}?>");
        }, $value);
    }

    /**
     * Store a raw block and return a unique raw placeholder.
     *
     * @param string $value
     *
     * @return string
     */
    protected function storeRawBlock($value) {
        return $this->getRawPlaceholder(
            array_push($this->rawBlocks, $value) - 1
        );
    }

    /**
     * Compile the component tags.
     *
     * @param string $value
     *
     * @return string
     */
    protected function compileComponentTags($value) {
        if (!$this->compilesComponentTags) {
            return $value;
        }

        return (new CView_Compiler_ComponentTagCompiler(
            $this->classComponentAliases,
            $this->classComponentNamespaces,
            $this
        ))->compile($value);
    }

    /**
     * Replace the raw placeholders with the original code stored in the raw blocks.
     *
     * @param string $result
     *
     * @return string
     */
    protected function restoreRawContent($result) {
        $result = preg_replace_callback('/' . $this->getRawPlaceholder('(\d+)') . '/', function ($matches) {
            return $this->rawBlocks[$matches[1]];
        }, $result);

        $this->rawBlocks = [];

        return $result;
    }

    /**
     * Get a placeholder to temporary mark the position of raw blocks.
     *
     * @param int|string $replace
     *
     * @return string
     */
    protected function getRawPlaceholder($replace) {
        return str_replace('#', $replace, '@__raw_block_#__@');
    }

    /**
     * Add the stored footers onto the given content.
     *
     * @param string $result
     *
     * @return string
     */
    protected function addFooters($result) {
        return ltrim($result, "\n")
                . "\n" . implode("\n", array_reverse($this->footer));
    }

    /**
     * Parse the tokens from the template.
     *
     * @param array $token
     *
     * @return string
     */
    protected function parseToken($token) {
        list($id, $content) = $token;

        if ($id == T_INLINE_HTML) {
            foreach ($this->compilers as $type) {
                $content = $this->{"compile{$type}"}($content);
            }
        }

        return $content;
    }

    /**
     * Execute the user defined extensions.
     *
     * @param string $value
     *
     * @return string
     */
    protected function compileExtensions($value) {
        foreach ($this->extensions as $compiler) {
            $value = $compiler($value, $this);
        }

        return $value;
    }

    /**
     * Compile Blade statements that start with "@".
     *
     * @param string $template
     *
     * @return string
     */
    protected function compileStatements($template) {
        preg_match_all('/\B@(@?\w+(?:::\w+)?)([ \t]*)(\( ( [\S\s]*? ) \))?/x', $template, $matches);

        $offset = 0;

        for ($i = 0; isset($matches[0][$i]); $i++) {
            $match = [
                $matches[0][$i],
                $matches[1][$i],
                $matches[2][$i],
                $matches[3][$i] ?: null,
                $matches[4][$i] ?: null,
            ];

            // Here we check to see if we have properly found the closing parenthesis by
            // regex pattern or not, and will recursively continue on to the next ")"
            // then check again until the tokenizer confirms we find the right one.
            while (isset($match[4])
                   && cstr::endsWith($match[0], ')')
                   && !$this->hasEvenNumberOfParentheses($match[0])) {
                if (($after = cstr::after($template, $match[0])) === $template) {
                    break;
                }

                $rest = cstr::before($after, ')');

                if (isset($matches[0][$i + 1]) && cstr::contains($rest . ')', $matches[0][$i + 1])) {
                    unset($matches[0][$i + 1]);
                    $i++;
                }

                $match[0] = $match[0] . $rest . ')';
                $match[3] = $match[3] . $rest . ')';
                $match[4] = $match[4] . $rest;
            }

            list($template, $offset) = $this->replaceFirstStatement(
                $match[0],
                $this->compileStatement($match),
                $template,
                $offset
            );
        }

        return $template;
    }

    /**
     * Replace the first match for a statement compilation operation.
     *
     * @param string $search
     * @param string $replace
     * @param string $subject
     * @param int    $offset
     *
     * @return array
     */
    protected function replaceFirstStatement($search, $replace, $subject, $offset) {
        $search = (string) $search;

        if ($search === '') {
            return $subject;
        }

        $position = strpos($subject, $search, $offset);

        if ($position !== false) {
            return [
                substr_replace($subject, $replace, $position, strlen($search)),
                $position + strlen($replace),
            ];
        }

        return [$subject, 0];
    }

    /**
     * Determine if the given expression has the same number of opening and closing parentheses.
     *
     * @param string $expression
     *
     * @return bool
     */
    protected function hasEvenNumberOfParentheses(string $expression) {
        $tokens = token_get_all('<?php ' . $expression);

        if (carr::last($tokens) !== ')') {
            return false;
        }

        $opening = 0;
        $closing = 0;

        foreach ($tokens as $token) {
            if ($token == ')') {
                $closing++;
            } elseif ($token == '(') {
                $opening++;
            }
        }

        return $opening === $closing;
    }

    /**
     * Compile a single Blade @ statement.
     *
     * @param array $match
     *
     * @return string
     */
    protected function compileStatement($match) {
        if (cstr::contains($match[1], '@')) {
            $match[0] = isset($match[3]) ? $match[1] . $match[3] : $match[1];
        } elseif (isset($this->customDirectives[$match[1]])) {
            $match[0] = $this->callCustomDirective($match[1], carr::get($match, 3));
        } elseif (method_exists($this, $method = 'compile' . ucfirst($match[1]))) {
            $match[0] = $this->$method(carr::get($match, 3));
        } else {
            return $match[0];
        }

        return isset($match[3]) ? $match[0] : $match[0] . $match[2];
    }

    /**
     * Call the given directive with the given value.
     *
     * @param string      $name
     * @param null|string $value
     *
     * @return string
     */
    protected function callCustomDirective($name, $value) {
        $value ??= '';
        if (cstr::startsWith($value, '(') && cstr::endsWith($value, ')')) {
            $value = cstr::substr($value, 1, -1);
        }

        return call_user_func($this->customDirectives[$name], trim($value));
    }

    /**
     * Strip the parentheses from the given expression.
     *
     * @param string $expression
     *
     * @return string
     */
    public function stripParentheses($expression) {
        if (cstr::startsWith($expression, '(')) {
            $expression = substr($expression, 1, -1);
        }

        return $expression;
    }

    /**
     * Register a custom Blade compiler.
     *
     * @param callable $compiler
     *
     * @return void
     */
    public function extend(callable $compiler) {
        $this->extensions[] = $compiler;
    }

    /**
     * Get the extensions used by the compiler.
     *
     * @return array
     */
    public function getExtensions() {
        return $this->extensions;
    }

    /**
     * Register an "if" statement directive.
     *
     * @param string   $name
     * @param callable $callback
     *
     * @return void
     */
    public function aliasIf($name, callable $callback) {
        $this->conditions[$name] = $callback;

        $this->directive($name, function ($expression) use ($name) {
            return $expression !== '' ? "<?php if (CView_Blade::check('{$name}', {$expression})): ?>" : "<?php if (CView_Blade::check('{$name}')): ?>";
        });

        $this->directive('unless' . $name, function ($expression) use ($name) {
            return $expression !== '' ? "<?php if (! CView_Blade::check('{$name}', {$expression})): ?>" : "<?php if (! CView_Blade::check('{$name}')): ?>";
        });

        $this->directive('else' . $name, function ($expression) use ($name) {
            return $expression !== '' ? "<?php elseif (\CView_Blade::check('{$name}', {$expression})): ?>" : "<?php elseif (\CView_Blade::check('{$name}')): ?>";
        });

        $this->directive('end' . $name, function () {
            return '<?php endif; ?>';
        });
    }

    /**
     * Check the result of a condition.
     *
     * @param string $name
     * @param array  $parameters
     *
     * @return bool
     */
    public function check($name, ...$parameters) {
        return call_user_func($this->conditions[$name], ...$parameters);
    }

    /**
     * Register a class-based component alias directive.
     *
     * @param string      $class
     * @param null|string $alias
     * @param string      $prefix
     *
     * @return void
     */
    public function component($class, $alias = null, $prefix = '') {
        if (!is_null($alias) && (cstr::contains($alias, '\\'))) {
            list($class, $alias) = [$alias, $class];
        }

        if (is_null($alias)) {
            $alias = cstr::contains($class, '\\View\\Components\\') ? c::collect(explode('\\', cstr::after($class, '\\View\\Components\\')))->map(function ($segment) {
                return cstr::kebab($segment);
            })->implode(':') : cstr::kebab(c::classBasename($class));
        }

        if (!empty($prefix)) {
            $alias = $prefix . '-' . $alias;
        }

        $this->classComponentAliases[$alias] = $class;
    }

    /**
     * Register an array of class-based components.
     *
     * @param array  $components
     * @param string $prefix
     *
     * @return void
     */
    public function components(array $components, $prefix = '') {
        foreach ($components as $key => $value) {
            if (is_numeric($key)) {
                $this->component($value, null, $prefix);
            } else {
                $this->component($key, $value, $prefix);
            }
        }
    }

    /**
     * Get the registered class component aliases.
     *
     * @return array
     */
    public function getClassComponentAliases() {
        return $this->classComponentAliases;
    }

    /**
     * Register a class-based component namespace.
     *
     * @param string $namespace
     * @param string $prefix
     *
     * @return void
     */
    public function componentNamespace($namespace, $prefix) {
        $this->classComponentNamespaces[$prefix] = $namespace;
    }

    /**
     * Get the registered anonymous component paths.
     *
     * @return array
     */
    public function getAnonymousComponentPaths() {
        return $this->anonymousComponentPaths;
    }

    /**
     * Get the registered anonymous component namespaces.
     *
     * @return array
     */
    public function getAnonymousComponentNamespaces() {
        return $this->anonymousComponentNamespaces;
    }

    /**
     * Get the registered class component namespaces.
     *
     * @return array
     */
    public function getClassComponentNamespaces() {
        return $this->classComponentNamespaces;
    }

    /**
     * Register a component alias directive.
     *
     * @param string      $path
     * @param null|string $alias
     *
     * @return void
     */
    public function aliasComponent($path, $alias = null) {
        $alias = $alias ?: carr::last(explode('.', $path));

        $this->directive($alias, function ($expression) use ($path) {
            return $expression ? "<?php \$__env->startComponent('{$path}', {$expression}); ?>" : "<?php \$__env->startComponent('{$path}'); ?>";
        });

        $this->directive('end' . $alias, function ($expression) {
            return '<?php echo $__env->renderComponent(); ?>';
        });
    }

    /**
     * Register an include alias directive.
     *
     * @param string      $path
     * @param null|string $alias
     *
     * @return void
     */
    public function aliasInclude($path, $alias = null) {
        $alias = $alias ?: carr::last(explode('.', $path));

        $this->directive($alias, function ($expression) use ($path) {
            $expression = $this->stripParentheses($expression) ?: '[]';

            return "<?php echo \$__env->make('{$path}', {$expression}, \carr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>";
        });
    }

    /**
     * Register a handler for custom directives.
     *
     * @param string   $name
     * @param callable $handler
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    public function directive($name, callable $handler) {
        if (!preg_match('/^\w+(?:::\w+)?$/x', $name)) {
            throw new InvalidArgumentException("The directive name [{$name}] is not valid. Directive names must only contain alphanumeric characters and underscores.");
        }

        $this->customDirectives[$name] = $handler;
    }

    /**
     * Get the list of custom directives.
     *
     * @return array
     */
    public function getCustomDirectives() {
        return $this->customDirectives;
    }

    /**
     * Register a new precompiler.
     *
     * @param callable $precompiler
     *
     * @return void
     */
    public function precompiler(callable $precompiler) {
        $this->precompilers[] = $precompiler;
    }

    /**
     * Set the echo format to be used by the compiler.
     *
     * @param string $format
     *
     * @return void
     */
    public function setEchoFormat($format) {
        $this->echoFormat = $format;
    }

    /**
     * Set the "echo" format to double encode entities.
     *
     * @return void
     */
    public function withDoubleEncoding() {
        $this->setEchoFormat('c::e(%s, true)');
    }

    /**
     * Set the "echo" format to not double encode entities.
     *
     * @return void
     */
    public function withoutDoubleEncoding() {
        $this->setEchoFormat('c::e(%s, false)');
    }

    /**
     * Indicate that component tags should not be compiled.
     *
     * @return void
     */
    public function withoutComponentTags() {
        $this->compilesComponentTags = false;
    }

    public function clearCompiled() {
        //$path = CView_Factory::compiledPath();
        $path = CF::config('view.compiled');
        $path = rtrim($path, '/');
        if (CF::appCode()) {
            $path .= DIRECTORY_SEPARATOR . CF::appCode();
        }
        $files = glob($path . '/*');

        CFile::delete($files);
    }
}
