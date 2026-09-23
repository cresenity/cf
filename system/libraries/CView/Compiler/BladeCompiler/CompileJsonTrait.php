<?php

trait CView_Compiler_BladeCompiler_CompileJsonTrait {
    /**
     * The default JSON encoding options.
     *
     * @var int
     */
    private $encodingOptions = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

    /**
     * Compile the JSON statement into valid PHP.
     *
     * @param string $expression
     *
     * @return string
     */
    protected function compileJson($expression) {
        $parts = $this->splitJsonArguments($this->stripParentheses($expression));

        $options = isset($parts[1]) ? trim($parts[1]) : $this->encodingOptions;

        $depth = isset($parts[2]) ? trim($parts[2]) : 512;

        return "<?php echo json_encode({$parts[0]}, {$options}, {$depth}) ?>";
    }

    /**
     * Compile the JSON statement on attribute into valid PHP.
     *
     * @param string $expression
     *
     * @return string
     */
    protected function compileJsonAttr($expression) {
        $parts = $this->splitJsonArguments($this->stripParentheses($expression));

        $options = isset($parts[1]) ? trim($parts[1]) : $this->encodingOptions;

        $depth = isset($parts[2]) ? trim($parts[2]) : 512;

        return "<?php echo htmlspecialchars(json_encode({$parts[0]}, {$options}, {$depth}), ENT_QUOTES, 'UTF-8') ?>";
    }

    /**
     * Split an `@json(value, options, depth)` expression into its top-level
     * comma-separated arguments, respecting nesting inside (), [], {} and
     * string literals - a plain `explode(',', ...)` corrupts any `value`
     * that itself contains a comma (a 3+ item array, or a nested call like
     * `c::env('KEY', $default)`), silently dropping whatever comes after the
     * 3rd fragment instead of producing valid PHP.
     *
     * @param string $expression
     *
     * @return array<int, string>
     */
    protected function splitJsonArguments($expression) {
        $tokens = token_get_all('<?php ' . $expression);

        $depth = 0;
        $parts = [''];
        $index = 0;

        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_OPEN_TAG) {
                continue;
            }

            $text = is_array($token) ? $token[1] : $token;

            if ($text === '(' || $text === '[' || $text === '{') {
                $depth++;
            } elseif ($text === ')' || $text === ']' || $text === '}') {
                $depth--;
            }

            if ($depth === 0 && $text === ',') {
                $parts[++$index] = '';

                continue;
            }

            $parts[$index] .= $text;
        }

        return array_map('trim', $parts);
    }
}
