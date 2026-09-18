<?php

/**
 * SCSS compiler.
 */
class CManager_Asset_SCSS_Compiler {
    use CManager_Asset_SCSS_Trait_CompilerOperatorTrait;
    use CManager_Asset_SCSS_Trait_CompilerLibTrait;
    use CManager_Asset_SCSS_Trait_CompilerHelperTrait;

    public static $VERSION = 'v0.0.12';

    public static $true = ['keyword', 'true'];

    public static $false = ['keyword', 'false'];

    public static $null = ['null'];

    public static $defaultValue = ['keyword', ''];

    public static $selfSelector = ['self'];

    protected static $operatorNames = [
        '+' => 'add',
        '-' => 'sub',
        '*' => 'mul',
        '/' => 'div',
        '%' => 'mod',

        '==' => 'eq',
        '!=' => 'neq',
        '<' => 'lt',
        '>' => 'gt',

        '<=' => 'lte',
        '>=' => 'gte',
    ];

    protected static $namespaces = [
        'special' => '%',
        'mixin' => '@',
        'function' => '^',
    ];

    protected static $unitTable = [
        'in' => [
            'in' => 1,
            'pt' => 72,
            'pc' => 6,
            'cm' => 2.54,
            'mm' => 25.4,
            'px' => 96,
        ]
    ];

    protected $importPaths = [''];

    protected $importCache = [];

    protected $userFunctions = [];

    protected $registeredVars = [];

    protected $numberPrecision = 5;

    protected $formatterClass = CManager_Asset_SCSS_Formatter_Nested::class;

    protected $formatter;

    // Built in functions

    protected static $lib_if = ['condition', 'if-true', 'if-false'];

    protected static $lib_index = ['list', 'value'];

    protected static $lib_rgb = ['red', 'green', 'blue'];

    protected static $lib_rgba = [
        ['red', 'color'],
        'green', 'blue', 'alpha'];

    protected static $lib_adjust_color = [
        'color', 'red', 'green', 'blue',
        'hue', 'saturation', 'lightness', 'alpha'
    ];

    protected static $lib_change_color = [
        'color', 'red', 'green', 'blue',
        'hue', 'saturation', 'lightness', 'alpha'
    ];

    protected static $lib_scale_color = [
        'color', 'red', 'green', 'blue',
        'hue', 'saturation', 'lightness', 'alpha'
    ];

    protected static $lib_ie_hex_str = ['color'];

    protected static $lib_red = ['color'];

    protected static $lib_green = ['color'];

    protected static $lib_blue = ['color'];

    protected static $lib_alpha = ['color'];

    protected static $lib_opacity = ['color'];

    // mix two colors
    protected static $lib_mix = ['color-1', 'color-2', 'weight'];

    protected static $lib_hsl = ['hue', 'saturation', 'lightness'];

    protected static $lib_hsla = ['hue', 'saturation',
        'lightness', 'alpha'];

    protected static $lib_hue = ['color'];

    protected static $lib_saturation = ['color'];

    protected static $lib_lightness = ['color'];

    protected static $lib_adjust_hue = ['color', 'degrees'];

    protected static $lib_lighten = ['color', 'amount'];

    protected static $lib_darken = ['color', 'amount'];

    protected static $lib_saturate = ['color', 'amount'];

    protected static $lib_desaturate = ['color', 'amount'];

    protected static $lib_grayscale = ['color'];

    protected static $lib_complement = ['color'];

    protected static $lib_invert = ['color'];

    // increases opacity by amount
    protected static $lib_opacify = ['color', 'amount'];

    protected static $lib_fade_in = ['color', 'amount'];

    // decreases opacity by amount
    protected static $lib_transparentize = ['color', 'amount'];

    protected static $lib_fade_out = ['color', 'amount'];

    protected static $lib_unquote = ['string'];

    protected static $lib_quote = ['string'];

    protected static $lib_percentage = ['value'];

    protected static $lib_round = ['value'];

    protected static $lib_floor = ['value'];

    protected static $lib_ceil = ['value'];

    protected static $lib_abs = ['value'];

    protected static $lib_length = ['list'];

    protected static $lib_nth = ['list', 'n'];

    protected static $lib_join = ['list1', 'list2', 'separator'];

    protected static $lib_append = ['list', 'val', 'separator'];

    protected static $lib_type_of = ['value'];

    protected static $lib_unit = ['number'];

    protected static $lib_unitless = ['number'];

    protected static $lib_comparable = ['number-1', 'number-2'];

    /**
     * CSS Colors.
     *
     * @see http://www.w3.org/TR/css3-color
     */
    protected static $cssColors = [
        'aliceblue' => '240,248,255',
        'antiquewhite' => '250,235,215',
        'aqua' => '0,255,255',
        'aquamarine' => '127,255,212',
        'azure' => '240,255,255',
        'beige' => '245,245,220',
        'bisque' => '255,228,196',
        'black' => '0,0,0',
        'blanchedalmond' => '255,235,205',
        'blue' => '0,0,255',
        'blueviolet' => '138,43,226',
        'brown' => '165,42,42',
        'burlywood' => '222,184,135',
        'cadetblue' => '95,158,160',
        'chartreuse' => '127,255,0',
        'chocolate' => '210,105,30',
        'coral' => '255,127,80',
        'cornflowerblue' => '100,149,237',
        'cornsilk' => '255,248,220',
        'crimson' => '220,20,60',
        'cyan' => '0,255,255',
        'darkblue' => '0,0,139',
        'darkcyan' => '0,139,139',
        'darkgoldenrod' => '184,134,11',
        'darkgray' => '169,169,169',
        'darkgreen' => '0,100,0',
        'darkgrey' => '169,169,169',
        'darkkhaki' => '189,183,107',
        'darkmagenta' => '139,0,139',
        'darkolivegreen' => '85,107,47',
        'darkorange' => '255,140,0',
        'darkorchid' => '153,50,204',
        'darkred' => '139,0,0',
        'darksalmon' => '233,150,122',
        'darkseagreen' => '143,188,143',
        'darkslateblue' => '72,61,139',
        'darkslategray' => '47,79,79',
        'darkslategrey' => '47,79,79',
        'darkturquoise' => '0,206,209',
        'darkviolet' => '148,0,211',
        'deeppink' => '255,20,147',
        'deepskyblue' => '0,191,255',
        'dimgray' => '105,105,105',
        'dimgrey' => '105,105,105',
        'dodgerblue' => '30,144,255',
        'firebrick' => '178,34,34',
        'floralwhite' => '255,250,240',
        'forestgreen' => '34,139,34',
        'fuchsia' => '255,0,255',
        'gainsboro' => '220,220,220',
        'ghostwhite' => '248,248,255',
        'gold' => '255,215,0',
        'goldenrod' => '218,165,32',
        'gray' => '128,128,128',
        'green' => '0,128,0',
        'greenyellow' => '173,255,47',
        'grey' => '128,128,128',
        'honeydew' => '240,255,240',
        'hotpink' => '255,105,180',
        'indianred' => '205,92,92',
        'indigo' => '75,0,130',
        'ivory' => '255,255,240',
        'khaki' => '240,230,140',
        'lavender' => '230,230,250',
        'lavenderblush' => '255,240,245',
        'lawngreen' => '124,252,0',
        'lemonchiffon' => '255,250,205',
        'lightblue' => '173,216,230',
        'lightcoral' => '240,128,128',
        'lightcyan' => '224,255,255',
        'lightgoldenrodyellow' => '250,250,210',
        'lightgray' => '211,211,211',
        'lightgreen' => '144,238,144',
        'lightgrey' => '211,211,211',
        'lightpink' => '255,182,193',
        'lightsalmon' => '255,160,122',
        'lightseagreen' => '32,178,170',
        'lightskyblue' => '135,206,250',
        'lightslategray' => '119,136,153',
        'lightslategrey' => '119,136,153',
        'lightsteelblue' => '176,196,222',
        'lightyellow' => '255,255,224',
        'lime' => '0,255,0',
        'limegreen' => '50,205,50',
        'linen' => '250,240,230',
        'magenta' => '255,0,255',
        'maroon' => '128,0,0',
        'mediumaquamarine' => '102,205,170',
        'mediumblue' => '0,0,205',
        'mediumorchid' => '186,85,211',
        'mediumpurple' => '147,112,219',
        'mediumseagreen' => '60,179,113',
        'mediumslateblue' => '123,104,238',
        'mediumspringgreen' => '0,250,154',
        'mediumturquoise' => '72,209,204',
        'mediumvioletred' => '199,21,133',
        'midnightblue' => '25,25,112',
        'mintcream' => '245,255,250',
        'mistyrose' => '255,228,225',
        'moccasin' => '255,228,181',
        'navajowhite' => '255,222,173',
        'navy' => '0,0,128',
        'oldlace' => '253,245,230',
        'olive' => '128,128,0',
        'olivedrab' => '107,142,35',
        'orange' => '255,165,0',
        'orangered' => '255,69,0',
        'orchid' => '218,112,214',
        'palegoldenrod' => '238,232,170',
        'palegreen' => '152,251,152',
        'paleturquoise' => '175,238,238',
        'palevioletred' => '219,112,147',
        'papayawhip' => '255,239,213',
        'peachpuff' => '255,218,185',
        'peru' => '205,133,63',
        'pink' => '255,192,203',
        'plum' => '221,160,221',
        'powderblue' => '176,224,230',
        'purple' => '128,0,128',
        'red' => '255,0,0',
        'rosybrown' => '188,143,143',
        'royalblue' => '65,105,225',
        'saddlebrown' => '139,69,19',
        'salmon' => '250,128,114',
        'sandybrown' => '244,164,96',
        'seagreen' => '46,139,87',
        'seashell' => '255,245,238',
        'sienna' => '160,82,45',
        'silver' => '192,192,192',
        'skyblue' => '135,206,235',
        'slateblue' => '106,90,205',
        'slategray' => '112,128,144',
        'slategrey' => '112,128,144',
        'snow' => '255,250,250',
        'springgreen' => '0,255,127',
        'steelblue' => '70,130,180',
        'tan' => '210,180,140',
        'teal' => '0,128,128',
        'thistle' => '216,191,216',
        'tomato' => '255,99,71',
        'transparent' => '0,0,0,0',
        'turquoise' => '64,224,208',
        'violet' => '238,130,238',
        'wheat' => '245,222,179',
        'white' => '255,255,255',
        'whitesmoke' => '245,245,245',
        'yellow' => '255,255,0',
        'yellowgreen' => '154,205,50'
    ];

    /**
     * Compile scss.
     *
     * @param string $code
     * @param string $name
     *
     * @return string
     */
    public function compile($code, $name = null) {
        $this->indentLevel = -1;
        $this->commentsSeen = [];
        $this->extends = [];
        $this->extendsMap = [];
        $this->parsedFiles = [];
        $this->env = null;
        $this->scope = null;

        $locale = setlocale(LC_NUMERIC, 0);
        setlocale(LC_NUMERIC, 'C');

        $this->parser = new CManager_Asset_SCSS_Parser($name);

        $tree = $this->parser->parse($code);

        $this->formatter = $this->createFormatter();

        $this->pushEnv($tree);
        $this->injectVariables($this->registeredVars);
        $this->compileRoot($tree);
        $this->popEnv();

        $out = $this->formatter->format($this->scope);

        setlocale(LC_NUMERIC, $locale);

        return $out;
    }

    protected function createFormatter() {
        $formatterClass = $this->formatterClass;

        return new $formatterClass();
    }

    protected function isSelfExtend($target, $origin) {
        foreach ($origin as $sel) {
            if (in_array($target, $sel)) {
                return true;
            }
        }

        return false;
    }

    protected function pushExtends($target, $origin) {
        if ($this->isSelfExtend($target, $origin)) {
            return;
        }

        $i = count($this->extends);
        $this->extends[] = [$target, $origin];

        foreach ($target as $part) {
            if (isset($this->extendsMap[$part])) {
                $this->extendsMap[$part][] = $i;
            } else {
                $this->extendsMap[$part] = [$i];
            }
        }
    }

    protected function makeOutputBlock($type, $selectors = null) {
        $out = new stdClass();
        $out->type = $type;
        $out->lines = [];
        $out->children = [];
        $out->parent = $this->scope;
        $out->selectors = $selectors;
        $out->depth = $this->env->depth;

        return $out;
    }

    protected function matchExtendsSingle($single, &$outOrigin) {
        $counts = [];
        foreach ($single as $part) {
            if (!is_string($part)) {
                return false;
            } // hmm

            if (isset($this->extendsMap[$part])) {
                foreach ($this->extendsMap[$part] as $idx) {
                    $counts[$idx]
                        = isset($counts[$idx]) ? $counts[$idx] + 1 : 1;
                }
            }
        }

        $outOrigin = [];
        $found = false;

        foreach ($counts as $idx => $count) {
            list($target, $origin) = $this->extends[$idx];

            // check count
            if ($count != count($target)) {
                continue;
            }

            // check if target is subset of single
            if (array_diff(array_intersect($single, $target), $target)) {
                continue;
            }

            $rem = array_diff($single, $target);

            foreach ($origin as $j => $new) {
                // prevent infinite loop when target extends itself
                foreach ($new as $new_selector) {
                    if (!array_diff($single, $new_selector)) {
                        continue 2;
                    }
                }

                $origin[$j][count($origin[$j]) - 1] = $this->combineSelectorSingle(end($new), $rem);
            }

            $outOrigin = array_merge($outOrigin, $origin);

            $found = true;
        }

        return $found;
    }

    protected function combineSelectorSingle($base, $other) {
        $tag = null;
        $out = [];

        foreach ([$base, $other] as $single) {
            foreach ($single as $part) {
                if (preg_match('/^[^\[.#:]/', $part)) {
                    $tag = $part;
                } else {
                    $out[] = $part;
                }
            }
        }

        if ($tag) {
            array_unshift($out, $tag);
        }

        return $out;
    }

    protected function matchExtends($selector, &$out, $from = 0, $initial = true) {
        foreach ($selector as $i => $part) {
            if ($i < $from) {
                continue;
            }

            if ($this->matchExtendsSingle($part, $origin)) {
                $before = array_slice($selector, 0, $i);
                $after = array_slice($selector, $i + 1);

                foreach ($origin as $new) {
                    $k = 0;

                    // remove shared parts
                    if ($initial) {
                        foreach ($before as $k => $val) {
                            if (!isset($new[$k]) || $val != $new[$k]) {
                                break;
                            }
                        }
                    }

                    $result = array_merge(
                        $before,
                        $k > 0 ? array_slice($new, $k) : $new,
                        $after
                    );

                    if ($result == $selector) {
                        continue;
                    }
                    $out[] = $result;

                    // recursively check for more matches
                    $this->matchExtends($result, $out, $i, false);

                    // selector sequence merging
                    if (!empty($before) && count($new) > 1) {
                        $result2 = array_merge(
                            array_slice($new, 0, -1),
                            $k > 0 ? array_slice($before, $k) : $before,
                            array_slice($new, -1),
                            $after
                        );

                        $out[] = $result2;
                    }
                }
            }
        }
    }

    protected function flattenSelectors($block, $parentKey = null) {
        if ($block->selectors) {
            $selectors = [];
            foreach ($block->selectors as $s) {
                $selectors[] = $s;
                if (!is_array($s)) {
                    continue;
                }
                // check extends
                if (!empty($this->extendsMap)) {
                    $this->matchExtends($s, $selectors);
                }
            }

            $block->selectors = [];
            $placeholderSelector = false;
            foreach ($selectors as $selector) {
                if ($this->hasSelectorPlaceholder($selector)) {
                    $placeholderSelector = true;

                    continue;
                }
                $block->selectors[] = $this->compileSelector($selector);
            }

            if ($placeholderSelector && 0 == count($block->selectors) && null !== $parentKey) {
                unset($block->parent->children[$parentKey]);

                return;
            }
        }

        foreach ($block->children as $key => $child) {
            $this->flattenSelectors($child, $key);
        }
    }

    protected function compileRoot($rootBlock) {
        $this->scope = $this->makeOutputBlock('root');

        $this->compileChildren($rootBlock->children, $this->scope);
        $this->flattenSelectors($this->scope);
    }

    protected function compileMedia($media) {
        $this->pushEnv($media);

        $mediaQuery = $this->compileMediaQuery($this->multiplyMedia($this->env));

        if (!empty($mediaQuery)) {
            $this->scope = $this->makeOutputBlock('media', [$mediaQuery]);

            $parentScope = $this->mediaParent($this->scope);

            $parentScope->children[] = $this->scope;

            // top level properties in a media cause it to be wrapped
            $needsWrap = false;
            foreach ($media->children as $child) {
                $type = $child[0];
                if ($type !== 'block' && $type !== 'media' && $type !== 'directive') {
                    $needsWrap = true;

                    break;
                }
            }

            if ($needsWrap) {
                $wrapped = (object) [
                    'selectors' => [],
                    'children' => $media->children
                ];
                $media->children = [['block', $wrapped]];
            }

            $this->compileChildren($media->children, $this->scope);

            $this->scope = $this->scope->parent;
        }

        $this->popEnv();
    }

    protected function mediaParent($scope) {
        while (!empty($scope->parent)) {
            if (!empty($scope->type) && $scope->type != 'media') {
                break;
            }
            $scope = $scope->parent;
        }

        return $scope;
    }

    protected function compileNestedBlock($block, $selectors) {
        // TODO refactor compileNestedBlock and compileMedia into same thing
        $this->pushEnv($block);

        $this->scope = $this->makeOutputBlock($block->type, $selectors);
        $this->scope->parent->children[] = $this->scope;
        $this->compileChildren($block->children, $this->scope);

        $this->scope = $this->scope->parent;
        $this->popEnv();
    }

    /**
     * Recursively compiles a block.
     *
     * A block is analogous to a CSS block in most cases. A single SCSS document
     * is encapsulated in a block when parsed, but it does not have parent tags
     * so all of its children appear on the root level when compiled.
     *
     * Blocks are made up of selectors and children.
     *
     * The children of a block are just all the blocks that are defined within.
     *
     * Compiling the block involves pushing a fresh environment on the stack,
     * and iterating through the props, compiling each one.
     *
     * @param \StdClass $block
     *
     * @see scss::compileChild()
     */
    protected function compileBlock($block) {
        $env = $this->pushEnv($block);

        $env->selectors
            = array_map([$this, 'evalSelector'], $block->selectors);

        $out = $this->makeOutputBlock(null, $this->multiplySelectors($env));
        $this->scope->children[] = $out;
        $this->compileChildren($block->children, $out);

        $this->popEnv();
    }

    protected function flattenSelectorSingle($single) {
        // joins together .classes and #ids
        $joined = [];
        foreach ($single as $part) {
            if (empty($joined)
                || !is_string($part)
                || preg_match('/[\[.:#%]/', $part)
            ) {
                $joined[] = $part;

                continue;
            }

            if (is_array(end($joined))) {
                $joined[] = $part;
            } else {
                $joined[count($joined) - 1] .= $part;
            }
        }

        return $joined;
    }

    protected function evalSelector($selector) {
        // replaces all the interpolates
        return array_map([$this, 'evalSelectorPart'], $selector);
    }

    protected function evalSelectorPart($piece) {
        foreach ($piece as &$p) {
            if (!is_array($p)) {
                continue;
            }

            switch ($p[0]) {
                case 'interpolate':
                    $p = $this->compileValue($p);

                    break;
                case 'string':
                    $p = $this->compileValue($p);

                    break;
            }
        }

        return $this->flattenSelectorSingle($piece);
    }

    protected function compileSelector($selector) {
        // compiles to string
        // self(&) should have been replaced by now

        if (!is_array($selector)) {
            return $selector;
        } // media and the like

        return implode(' ', array_map(
            [$this, 'compileSelectorPart'],
            $selector
        ));
    }

    protected function compileSelectorPart($piece) {
        foreach ($piece as &$p) {
            if (!is_array($p)) {
                continue;
            }

            switch ($p[0]) {
                case 'self':
                    $p = '&';

                    break;
                default:
                    $p = $this->compileValue($p);

                    break;
            }
        }

        return implode($piece);
    }

    protected function hasSelectorPlaceholder($selector) {
        if (!is_array($selector)) {
            return false;
        }

        foreach ($selector as $parts) {
            foreach ($parts as $part) {
                if ('%' == $part[0]) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function compileChildren($stms, $out) {
        foreach ($stms as $stm) {
            $ret = $this->compileChild($stm, $out);
            if (isset($ret)) {
                return $ret;
            }
        }
    }

    protected function compileMediaQuery($queryList) {
        $out = '@media';
        $first = true;
        foreach ($queryList as $query) {
            $type = null;
            $parts = [];
            foreach ($query as $q) {
                switch ($q[0]) {
                    case 'mediaType':
                        if ($type) {
                            $type = $this->mergeMediaTypes($type, array_map([$this, 'compileValue'], array_slice($q, 1)));
                            if (empty($type)) { // merge failed
                                return null;
                            }
                        } else {
                            $type = array_map([$this, 'compileValue'], array_slice($q, 1));
                        }

                        break;
                    case 'mediaExp':
                        if (isset($q[2])) {
                            $parts[] = '(' . $this->compileValue($q[1]) . $this->formatter->assignSeparator . $this->compileValue($q[2]) . ')';
                        } else {
                            $parts[] = '(' . $this->compileValue($q[1]) . ')';
                        }

                        break;
                }
            }
            if ($type) {
                array_unshift($parts, implode(' ', array_filter($type)));
            }
            if (!empty($parts)) {
                if ($first) {
                    $first = false;
                    $out .= ' ';
                } else {
                    $out .= $this->formatter->tagSeparator;
                }
                $out .= implode(' and ', $parts);
            }
        }

        return $out;
    }

    protected function mergeMediaTypes($type1, $type2) {
        if (empty($type1)) {
            return $type2;
        }
        if (empty($type2)) {
            return $type1;
        }
        $m1 = '';
        $t1 = '';
        if (count($type1) > 1) {
            $m1 = strtolower($type1[0]);
            $t1 = strtolower($type1[1]);
        } else {
            $t1 = strtolower($type1[0]);
        }
        $m2 = '';
        $t2 = '';
        if (count($type2) > 1) {
            $m2 = strtolower($type2[0]);
            $t2 = strtolower($type2[1]);
        } else {
            $t2 = strtolower($type2[0]);
        }
        if (($m1 == 'not') ^ ($m2 == 'not')) {
            if ($t1 == $t2) {
                return null;
            }

            return [
                $m1 == 'not' ? $m2 : $m1,
                $m1 == 'not' ? $t2 : $t1
            ];
        } elseif ($m1 == 'not' && $m2 == 'not') {
            // CSS has no way of representing "neither screen nor print"
            if ($t1 != $t2) {
                return null;
            }

            return ['not', $t1];
        } elseif ($t1 != $t2) {
            return null;
        } else { // t1 == t2, neither m1 nor m2 are "not"
            return [empty($m1) ? $m2 : $m1, $t1];
        }
    }

    protected function compileImport($rawPath, $out) {
        // returns true if the value was something that could be imported
        if ($rawPath[0] == 'string') {
            $path = $this->compileStringContent($rawPath);
            if ($path = $this->findImport($path)) {
                $this->importFile($path, $out);

                return true;
            }

            return false;
        }
        if ($rawPath[0] == 'list') {
            // handle a list of strings
            if (count($rawPath[2]) == 0) {
                return false;
            }
            foreach ($rawPath[2] as $path) {
                if ($path[0] != 'string') {
                    return false;
                }
            }

            foreach ($rawPath[2] as $path) {
                $this->compileImport($path, $out);
            }

            return true;
        }

        return false;
    }

    protected function compileChild($child, $out) {
        // return a value to halt execution
        $this->sourcePos = isset($child[-1]) ? $child[-1] : -1;
        $this->sourceParser = isset($child[-2]) ? $child[-2] : $this->parser;

        switch ($child[0]) {
            case 'import':
                list(, $rawPath) = $child;
                $rawPath = $this->reduce($rawPath);
                if (!$this->compileImport($rawPath, $out)) {
                    $out->lines[] = '@import ' . $this->compileValue($rawPath) . ';';
                }

                break;
            case 'directive':
                list(, $directive) = $child;
                $s = '@' . $directive->name;
                if (!empty($directive->value)) {
                    $s .= ' ' . $this->compileValue($directive->value);
                }
                $this->compileNestedBlock($directive, [$s]);

                break;
            case 'media':
                $this->compileMedia($child[1]);

                break;
            case 'block':
                $this->compileBlock($child[1]);

                break;
            case 'charset':
                $out->lines[] = '@charset ' . $this->compileValue($child[1]) . ';';

                break;
            case 'assign':
                list(, $name, $value) = $child;
                if ($name[0] == 'var') {
                    $isDefault = !empty($child[3]);

                    if ($isDefault) {
                        $existingValue = $this->get($name[1], true);
                        $shouldSet = $existingValue === true || $existingValue == self::$null;
                    }

                    if (!$isDefault || $shouldSet) {
                        $this->set($name[1], $this->reduce($value));
                    }

                    break;
                }

                // if the value reduces to null from something else then
                // the property should be discarded
                if ($value[0] != 'null') {
                    $value = $this->reduce($value);
                    if ($value[0] == 'null') {
                        break;
                    }
                }

                $compiledValue = $this->compileValue($value);
                $out->lines[] = $this->formatter->property(
                    $this->compileValue($name),
                    $compiledValue
                );

                break;
            case 'comment':
                $out->lines[] = $child[1];

                break;
            case 'mixin':
            case 'function':
                list(, $block) = $child;
                $this->set(self::$namespaces[$block->type] . $block->name, $block);

                break;
            case 'extend':
                list(, $selectors) = $child;
                foreach ($selectors as $sel) {
                    // only use the first one
                    $sel = current($this->evalSelector($sel));
                    $this->pushExtends($sel, $out->selectors);
                }

                break;
            case 'if':
                list(, $if) = $child;
                if ($this->isTruthy($this->reduce($if->cond, true))) {
                    return $this->compileChildren($if->children, $out);
                } else {
                    foreach ($if->cases as $case) {
                        if ($case->type == 'else'
                            || $case->type == 'elseif' && $this->isTruthy($this->reduce($case->cond))
                        ) {
                            return $this->compileChildren($case->children, $out);
                        }
                    }
                }

                break;
            case 'return':
                return $this->reduce($child[1], true);
            case 'each':
                list(, $each) = $child;
                $list = $this->coerceList($this->reduce($each->list));
                foreach ($list[2] as $item) {
                    $this->pushEnv();
                    $this->set($each->var, $item);
                    // TODO: allow return from here
                    $this->compileChildren($each->children, $out);
                    $this->popEnv();
                }

                break;
            case 'while':
                list(, $while) = $child;
                while ($this->isTruthy($this->reduce($while->cond, true))) {
                    $ret = $this->compileChildren($while->children, $out);
                    if ($ret) {
                        return $ret;
                    }
                }

                break;
            case 'for':
                list(, $for) = $child;
                $start = $this->reduce($for->start, true);
                $start = $start[1];
                $end = $this->reduce($for->end, true);
                $end = $end[1];
                $d = $start < $end ? 1 : -1;

                while (true) {
                    if ((!$for->until && $start - $d == $end)
                        || ($for->until && $start == $end)
                    ) {
                        break;
                    }

                    $this->set($for->var, ['number', $start, '']);
                    $start += $d;

                    $ret = $this->compileChildren($for->children, $out);
                    if ($ret) {
                        return $ret;
                    }
                }

                break;
            case 'nestedprop':
                list(, $prop) = $child;
                $prefixed = [];
                $prefix = $this->compileValue($prop->prefix) . '-';
                foreach ($prop->children as $child) {
                    if ($child[0] == 'assign') {
                        array_unshift($child[1][2], $prefix);
                    }
                    if ($child[0] == 'nestedprop') {
                        array_unshift($child[1]->prefix[2], $prefix);
                    }
                    $prefixed[] = $child;
                }
                $this->compileChildren($prefixed, $out);

                break;
            case 'include': // including a mixin
                list(, $name, $argValues, $content) = $child;
                $mixin = $this->get(self::$namespaces['mixin'] . $name, false);
                if (!$mixin) {
                    $this->throwError("Undefined mixin {$name}");
                }

                $callingScope = $this->env;

                // push scope, apply args
                $this->pushEnv();
                if ($this->env->depth > 0) {
                    $this->env->depth--;
                }

                if (isset($content)) {
                    $content->scope = $callingScope;
                    $this->setRaw(self::$namespaces['special'] . 'content', $content);
                }

                if (isset($mixin->args)) {
                    $this->applyArguments($mixin->args, $argValues);
                }

                foreach ($mixin->children as $child) {
                    $this->compileChild($child, $out);
                }

                $this->popEnv();

                break;
            case 'mixin_content':
                $content = $this->get(self::$namespaces['special'] . 'content');
                if (!isset($content)) {
                    $this->throwError('Expected @content inside of mixin');
                }

                $strongTypes = ['include', 'block', 'for', 'while'];
                foreach ($content->children as $child) {
                    $this->storeEnv = (in_array($child[0], $strongTypes))
                        ? null
                        : $content->scope;

                    $this->compileChild($child, $out);
                }

                unset($this->storeEnv);

                break;
            case 'debug':
                list(, $value, $pos) = $child;
                $line = $this->parser->getLineNo($pos);
                $value = $this->compileValue($this->reduce($value, true));
                fwrite(STDERR, "Line {$line} DEBUG: {$value}\n");

                break;
            default:
                $this->throwError("unknown child type: {$child[0]}");
        }
    }

    protected function expToString($exp) {
        list(, $op, $left, $right, $inParens, $whiteLeft, $whiteRight) = $exp;
        $content = [$this->reduce($left)];
        if ($whiteLeft) {
            $content[] = ' ';
        }
        $content[] = $op;
        if ($whiteRight) {
            $content[] = ' ';
        }
        $content[] = $this->reduce($right);

        return ['string', '', $content];
    }

    protected function isTruthy($value) {
        return $value != self::$false && $value != self::$null;
    }

    protected function shouldEval($value) {
        // should $value cause its operand to eval
        switch ($value[0]) {
            case 'exp':
                if ($value[1] == '/') {
                    return $this->shouldEval($value[2], $value[3]);
                }
                // no break
            case 'var':
            case 'fncall':
                return true;
        }

        return false;
    }

    protected function reduce($value, $inExp = false) {
        list($type) = $value;
        switch ($type) {
            case 'exp':
                list(, $op, $left, $right, $inParens) = $value;
                $opName = isset(self::$operatorNames[$op]) ? self::$operatorNames[$op] : $op;

                $inExp = $inExp || $this->shouldEval($left) || $this->shouldEval($right);

                $left = $this->reduce($left, true);
                $right = $this->reduce($right, true);

                // only do division in special cases
                if ($opName == 'div' && !$inParens && !$inExp) {
                    if ($left[0] != 'color' && $right[0] != 'color') {
                        return $this->expToString($value);
                    }
                }

                $left = $this->coerceForExpression($left);
                $right = $this->coerceForExpression($right);

                $ltype = $left[0];
                $rtype = $right[0];

                // this tries:
                // 1. op_[op name]_[left type]_[right type]
                // 2. op_[left type]_[right type] (passing the op as first arg
                // 3. op_[op name]
                $fn = "op_{$opName}_{$ltype}_{$rtype}";
                if (is_callable([$this, $fn])
                    || (($fn = "op_{$ltype}_{$rtype}")
                    && is_callable([$this, $fn])
                    && $passOp = true)
                    || (($fn = "op_{$opName}")
                    && is_callable([$this, $fn])
                    && $genOp = true)
                ) {
                    $unitChange = false;
                    if (!isset($genOp)
                        && $left[0] == 'number' && $right[0] == 'number'
                    ) {
                        if ($opName == 'mod' && $right[2] != '') {
                            $this->throwError("Cannot modulo by a number with units: {$right[1]}{$right[2]}.");
                        }

                        $unitChange = true;
                        $emptyUnit = $left[2] == '' || $right[2] == '';
                        $targetUnit = '' != $left[2] ? $left[2] : $right[2];

                        if ($opName != 'mul') {
                            $left[2] = '' != $left[2] ? $left[2] : $targetUnit;
                            $right[2] = '' != $right[2] ? $right[2] : $targetUnit;
                        }

                        if ($opName != 'mod') {
                            $left = $this->normalizeNumber($left);
                            $right = $this->normalizeNumber($right);
                        }

                        if ($opName == 'div' && !$emptyUnit && $left[2] == $right[2]) {
                            $targetUnit = '';
                        }

                        if ($opName == 'mul') {
                            $left[2] = '' != $left[2] ? $left[2] : $right[2];
                            $right[2] = '' != $right[2] ? $right[2] : $left[2];
                        } elseif ($opName == 'div' && $left[2] == $right[2]) {
                            $left[2] = '';
                            $right[2] = '';
                        }
                    }

                    $shouldEval = $inParens || $inExp;
                    if (isset($passOp)) {
                        $out = $this->$fn($op, $left, $right, $shouldEval);
                    } else {
                        $out = $this->$fn($left, $right, $shouldEval);
                    }

                    if (isset($out)) {
                        if ($unitChange && $out[0] == 'number') {
                            $out = $this->coerceUnit($out, $targetUnit);
                        }

                        return $out;
                    }
                }

                return $this->expToString($value);
            case 'unary':
                list(, $op, $exp, $inParens) = $value;
                $inExp = $inExp || $this->shouldEval($exp);

                $exp = $this->reduce($exp);
                if ($exp[0] == 'number') {
                    switch ($op) {
                        case '+':
                            return $exp;
                        case '-':
                            $exp[1] *= -1;

                            return $exp;
                    }
                }

                if ($op == 'not') {
                    if ($inExp || $inParens) {
                        if ($exp == self::$false) {
                            return self::$true;
                        } else {
                            return self::$false;
                        }
                    } else {
                        $op = $op . ' ';
                    }
                }

                return ['string', '', [$op, $exp]];
            case 'var':
                list(, $name) = $value;

                return $this->reduce($this->get($name));
            case 'list':
                foreach ($value[2] as &$item) {
                    $item = $this->reduce($item);
                }

                return $value;
            case 'string':
                foreach ($value[2] as &$item) {
                    if (is_array($item)) {
                        $item = $this->reduce($item);
                    }
                }

                return $value;
            case 'interpolate':
                $value[1] = $this->reduce($value[1]);

                return $value;
            case 'fncall':
                list(, $name, $argValues) = $value;

                // user defined function?
                $func = $this->get(self::$namespaces['function'] . $name, false);
                if ($func) {
                    $this->pushEnv();

                    // set the args
                    if (isset($func->args)) {
                        $this->applyArguments($func->args, $argValues);
                    }

                    // throw away lines and children
                    $tmp = (object) [
                        'lines' => [],
                        'children' => []
                    ];
                    $ret = $this->compileChildren($func->children, $tmp);
                    $this->popEnv();

                    return !isset($ret) ? self::$defaultValue : $ret;
                }

                // built in function
                if ($this->callBuiltin($name, $argValues, $returnValue)) {
                    return $returnValue;
                }

                // need to flatten the arguments into a list
                $listArgs = [];
                foreach ((array) $argValues as $arg) {
                    if (empty($arg[0])) {
                        $listArgs[] = $this->reduce($arg[1]);
                    }
                }

                return ['function', $name, ['list', ',', $listArgs]];
            default:
                return $value;
        }
    }

    public function normalizeValue($value) {
        $value = $this->coerceForExpression($this->reduce($value));
        list($type) = $value;

        switch ($type) {
            case 'list':
                $value = $this->extractInterpolation($value);
                if ($value[0] != 'list') {
                    return ['keyword', $this->compileValue($value)];
                }
                foreach ($value[2] as $key => $item) {
                    $value[2][$key] = $this->normalizeValue($item);
                }

                return $value;
            case 'number':
                return $this->normalizeNumber($value);
            default:
                return $value;
        }
    }

    protected function normalizeNumber($number) {
        // just does physical lengths for now
        list(, $value, $unit) = $number;
        if (isset(self::$unitTable['in'][$unit])) {
            $conv = self::$unitTable['in'][$unit];

            return ['number', $value / $conv, 'in'];
        }

        return $number;
    }

    protected function coerceUnit($number, $unit) {
        // $number should be normalized
        list(, $value, $baseUnit) = $number;
        if (isset(self::$unitTable[$baseUnit][$unit])) {
            $value = $value * self::$unitTable[$baseUnit][$unit];
        }

        return ['number', $value, $unit];
    }

    public function toBool($thing) {
        return $thing ? self::$true : self::$false;
    }

    /**
     * Compiles a primitive value into a CSS property value.
     *
     * Values in scssphp are typed by being wrapped in arrays, their format is
     * typically:
     *
     *     array(type, contents [, additional_contents]*)
     *
     * The input is expected to be reduced. This function will not work on
     * things like expressions and variables.
     *
     * @param array $value
     */
    protected function compileValue($value) {
        $value = $this->reduce($value);

        list($type) = $value;
        switch ($type) {
            case 'keyword':
                return $value[1];
            case 'color':
                // [1] - red component (either number for a %)
                // [2] - green component
                // [3] - blue component
                // [4] - optional alpha component
                list(, $r, $g, $b) = $value;

                $r = round($r);
                $g = round($g);
                $b = round($b);

                if (count($value) == 5 && $value[4] != 1) { // rgba
                    return 'rgba(' . $r . ', ' . $g . ', ' . $b . ', ' . $value[4] . ')';
                }

                $h = sprintf('#%02x%02x%02x', $r, $g, $b);

                // Converting hex color to short notation (e.g. #003399 to #039)
                if ($h[1] === $h[2] && $h[3] === $h[4] && $h[5] === $h[6]) {
                    $h = '#' . $h[1] . $h[3] . $h[5];
                }

                return $h;
            case 'number':
                return round($value[1], $this->numberPrecision) . $value[2];
            case 'string':
                return $value[1] . $this->compileStringContent($value) . $value[1];
            case 'function':
                $args = !empty($value[2]) ? $this->compileValue($value[2]) : '';

                return "{$value[1]}({$args})";
            case 'list':
                $value = $this->extractInterpolation($value);
                if ($value[0] != 'list') {
                    return $this->compileValue($value);
                }

                list(, $delim, $items) = $value;

                $filtered = [];
                foreach ($items as $item) {
                    if ($item[0] == 'null') {
                        continue;
                    }
                    $filtered[] = $this->compileValue($item);
                }

                return implode("{$delim} ", $filtered);
            case 'interpolated':
                //node created by extractInterpolation
                list(, $interpolate, $left, $right) = $value;
                list(, , $whiteLeft, $whiteRight) = $interpolate;

                $left = count($left[2]) > 0
                    ? $this->compileValue($left) . $whiteLeft : '';

                $right = count($right[2]) > 0
                    ? $whiteRight . $this->compileValue($right) : '';

                return $left . $this->compileValue($interpolate) . $right;

            case 'interpolate':
                // raw parse node
                list(, $exp) = $value;

                // strip quotes if it's a string
                $reduced = $this->reduce($exp);
                switch ($reduced[0]) {
                    case 'string':
                        $reduced = ['keyword',
                            $this->compileStringContent($reduced)];

                        break;
                    case 'null':
                        $reduced = ['keyword', ''];
                }

                return $this->compileValue($reduced);
            case 'null':
                return 'null';
            default:
                $this->throwError("unknown value type: {$type}");
        }
    }

    protected function compileStringContent($string) {
        $parts = [];
        foreach ($string[2] as $part) {
            if (is_array($part)) {
                $parts[] = $this->compileValue($part);
            } else {
                $parts[] = $part;
            }
        }

        return implode($parts);
    }

    protected function extractInterpolation($list) {
        // doesn't need to be recursive, compileValue will handle that
        $items = $list[2];
        foreach ($items as $i => $item) {
            if ($item[0] == 'interpolate') {
                $before = ['list', $list[1], array_slice($items, 0, $i)];
                $after = ['list', $list[1], array_slice($items, $i + 1)];

                return ['interpolated', $item, $before, $after];
            }
        }

        return $list;
    }

    protected function multiplySelectors($env) {
        // find the final set of selectors
        $envs = [];
        while (null !== $env) {
            if (!empty($env->selectors)) {
                $envs[] = $env;
            }
            $env = $env->parent;
        }

        $selectors = [];
        $parentSelectors = [[]];
        while ($env = array_pop($envs)) {
            $selectors = [];
            foreach ($env->selectors as $selector) {
                foreach ($parentSelectors as $parent) {
                    $selectors[] = $this->joinSelectors($parent, $selector);
                }
            }
            $parentSelectors = $selectors;
        }

        return $selectors;
    }

    protected function joinSelectors($parent, $child) {
        // looks for & to replace, or append parent before child
        $setSelf = false;
        $out = [];
        foreach ($child as $part) {
            $newPart = [];
            foreach ($part as $p) {
                if ($p == self::$selfSelector) {
                    $setSelf = true;
                    foreach ($parent as $i => $parentPart) {
                        if ($i > 0) {
                            $out[] = $newPart;
                            $newPart = [];
                        }

                        foreach ($parentPart as $pp) {
                            $newPart[] = $pp;
                        }
                    }
                } else {
                    $newPart[] = $p;
                }
            }

            $out[] = $newPart;
        }

        return $setSelf ? $out : array_merge($parent, $child);
    }

    protected function multiplyMedia($env, $childQueries = null) {
        if (!isset($env)
            || !empty($env->block->type) && $env->block->type != 'media'
        ) {
            return $childQueries;
        }

        // plain old block, skip
        if (empty($env->block->type)) {
            return $this->multiplyMedia($env->parent, $childQueries);
        }

        $parentQueries = $env->block->queryList;
        if ($childQueries == null) {
            $childQueries = $parentQueries;
        } else {
            $originalQueries = $childQueries;
            $childQueries = [];

            foreach ($parentQueries as $parentQuery) {
                foreach ($originalQueries as $childQuery) {
                    $childQueries[] = array_merge($parentQuery, $childQuery);
                }
            }
        }

        return $this->multiplyMedia($env->parent, $childQueries);
    }

    protected function coerceList($item, $delim = ',') {
        // convert something to list
        if (isset($item) && $item[0] == 'list') {
            return $item;
        }

        return ['list', $delim, !isset($item) ? [] : [$item]];
    }

    protected function applyArguments($argDef, $argValues) {
        $hasVariable = false;
        $args = [];
        foreach ($argDef as $i => $arg) {
            list($name, $default, $isVariable) = $argDef[$i];
            $args[$name] = [$i, $name, $default, $isVariable];
            $hasVariable |= $isVariable;
        }

        $keywordArgs = [];
        $deferredKeywordArgs = [];
        $remaining = [];
        // assign the keyword args
        foreach ((array) $argValues as $arg) {
            if (!empty($arg[0])) {
                if (!isset($args[$arg[0][1]])) {
                    if ($hasVariable) {
                        $deferredKeywordArgs[$arg[0][1]] = $arg[1];
                    } else {
                        $this->throwError("Mixin or function doesn't have an argument named $%s.", $arg[0][1]);
                    }
                } elseif ($args[$arg[0][1]][0] < count($remaining)) {
                    $this->throwError('The argument $%s was passed both by position and by name.', $arg[0][1]);
                } else {
                    $keywordArgs[$arg[0][1]] = $arg[1];
                }
            } elseif (count($keywordArgs)) {
                $this->throwError('Positional arguments must come before keyword arguments.');
            } elseif ($arg[2] == true) {
                $val = $this->reduce($arg[1], true);
                if ($val[0] == 'list') {
                    foreach ($val[2] as $name => $item) {
                        if (!is_numeric($name)) {
                            $keywordArgs[$name] = $item;
                        } else {
                            $remaining[] = $item;
                        }
                    }
                } else {
                    $remaining[] = $val;
                }
            } else {
                $remaining[] = $arg[1];
            }
        }

        foreach ($args as $arg) {
            list($i, $name, $default, $isVariable) = $arg;
            if ($isVariable) {
                $val = ['list', ',', []];
                for ($count = count($remaining); $i < $count; $i++) {
                    $val[2][] = $remaining[$i];
                }
                foreach ($deferredKeywordArgs as $itemName => $item) {
                    $val[2][$itemName] = $item;
                }
            } elseif (isset($remaining[$i])) {
                $val = $remaining[$i];
            } elseif (isset($keywordArgs[$name])) {
                $val = $keywordArgs[$name];
            } elseif (!empty($default)) {
                $val = $default;
            } else {
                $this->throwError("Missing argument {$name}");
            }

            $this->set($name, $this->reduce($val, true), true);
        }
    }

    protected function pushEnv($block = null) {
        $env = new stdClass();
        $env->parent = $this->env;
        $env->store = [];
        $env->block = $block;
        $env->depth = isset($this->env->depth) ? $this->env->depth + 1 : 0;

        $this->env = $env;

        return $env;
    }

    protected function normalizeName($name) {
        return str_replace('-', '_', $name);
    }

    protected function getStoreEnv() {
        return isset($this->storeEnv) ? $this->storeEnv : $this->env;
    }

    protected function set($name, $value, $shadow = false) {
        $name = $this->normalizeName($name);

        if ($shadow) {
            $this->setRaw($name, $value);
        } else {
            $this->setExisting($name, $value);
        }
    }

    protected function setExisting($name, $value, $env = null) {
        if (!isset($env)) {
            $env = $this->getStoreEnv();
        }

        if (isset($env->store[$name]) || !isset($env->parent)) {
            $env->store[$name] = $value;
        } else {
            $this->setExisting($name, $value, $env->parent);
        }
    }

    protected function setRaw($name, $value) {
        $env = $this->getStoreEnv();
        $env->store[$name] = $value;
    }

    public function get($name, $defaultValue = null, $env = null) {
        $name = $this->normalizeName($name);

        if (!isset($env)) {
            $env = $this->getStoreEnv();
        }
        if (!isset($defaultValue)) {
            $defaultValue = self::$defaultValue;
        }

        if (isset($env->store[$name])) {
            return $env->store[$name];
        } elseif (isset($env->parent)) {
            return $this->get($name, $defaultValue, $env->parent);
        }

        return $defaultValue; // found nothing
    }

    protected function injectVariables(array $args) {
        if (empty($args)) {
            return;
        }

        $parser = new CManager_Asset_SCSS_Parser(__METHOD__, false);

        foreach ($args as $name => $strValue) {
            if ($name[0] === '$') {
                $name = substr($name, 1);
            }

            $parser->env = null;
            $parser->count = 0;
            $parser->buffer = (string) $strValue;
            $parser->inParens = false;
            $parser->eatWhiteDefault = true;
            $parser->insertComments = true;

            if (!$parser->valueList($value)) {
                throw new Exception("failed to parse passed in variable {$name}: {$strValue}");
            }

            $this->set($name, $value);
        }
    }

    /**
     * Set variables.
     *
     * @param array $variables
     */
    public function setVariables(array $variables) {
        $this->registeredVars = array_merge($this->registeredVars, $variables);
    }

    /**
     * Unset variable.
     *
     * @param string $name
     */
    public function unsetVariable($name) {
        unset($this->registeredVars[$name]);
    }

    protected function popEnv() {
        $env = $this->env;
        $this->env = $this->env->parent;

        return $env;
    }

    public function getParsedFiles() {
        return $this->parsedFiles;
    }

    public function addImportPath($path) {
        $this->importPaths[] = $path;
    }

    public function setImportPaths($path) {
        $this->importPaths = (array) $path;
    }

    public function setNumberPrecision($numberPrecision) {
        $this->numberPrecision = $numberPrecision;
    }

    public function setFormatter($formatterName) {
        $this->formatter = $formatterName;
    }

    public function registerFunction($name, $func) {
        $this->userFunctions[$this->normalizeName($name)] = $func;
    }

    public function unregisterFunction($name) {
        unset($this->userFunctions[$this->normalizeName($name)]);
    }

    protected function importFile($path, $out) {
        // see if tree is cached
        $realPath = realpath($path);
        if (isset($this->importCache[$realPath])) {
            $tree = $this->importCache[$realPath];
        } else {
            $code = file_get_contents($path);
            $parser = new CManager_Asset_SCSS_Parser($path, false);
            $tree = $parser->parse($code);
            $this->parsedFiles[] = $path;

            $this->importCache[$realPath] = $tree;
        }

        $pi = pathinfo($path);
        array_unshift($this->importPaths, $pi['dirname']);
        $this->compileChildren($tree->children, $out);
        array_shift($this->importPaths);
    }

    public function findImport($url) {
        // results the file path for an import url if it exists
        $urls = [];

        // for "normal" scss imports (ignore vanilla css and external requests)
        if (!preg_match('/\.css|^http:\/\/$/', $url)) {
            // try both normal and the _partial filename
            $urls = [$url, preg_replace('/[^\/]+$/', '_\0', $url)];
        }

        foreach ($this->importPaths as $dir) {
            if (is_string($dir)) {
                // check urls for normal import paths
                foreach ($urls as $full) {
                    $full = $dir
                        . (!empty($dir) && substr($dir, -1) != '/' ? '/' : '')
                        . $full;

                    if ($this->fileExists($file = $full . '.scss')
                        || $this->fileExists($file = $full)
                    ) {
                        return $file;
                    }
                }
            } else {
                // check custom callback for import path
                $file = call_user_func($dir, $url, $this);
                if ($file !== null) {
                    return $file;
                }
            }
        }

        return null;
    }

    protected function fileExists($name) {
        return is_file($name);
    }

    protected function callBuiltin($name, $args, &$returnValue) {
        // try a lib function
        $name = $this->normalizeName($name);
        $libName = 'lib_' . $name;
        $f = [$this, $libName];
        if (is_callable($f)) {
            $prototype = isset(self::$$libName) ? self::$$libName : null;
            $sorted = $this->sortArgs($prototype, $args);
            foreach ($sorted as &$val) {
                $val = $this->reduce($val, true);
            }
            $returnValue = call_user_func($f, $sorted, $this);
        } elseif (isset($this->userFunctions[$name])) {
            // see if we can find a user function
            $fn = $this->userFunctions[$name];

            foreach ($args as &$val) {
                $val = $this->reduce($val[1], true);
            }

            $returnValue = call_user_func($fn, $args, $this);
        }

        if (isset($returnValue)) {
            // coerce a php value into a scss one
            if (is_numeric($returnValue)) {
                $returnValue = ['number', $returnValue, ''];
            } elseif (is_bool($returnValue)) {
                $returnValue = $returnValue ? self::$true : self::$false;
            } elseif (!is_array($returnValue)) {
                $returnValue = ['keyword', $returnValue];
            }

            return true;
        }

        return false;
    }

    protected function sortArgs($prototype, $args) {
        // sorts any keyword arguments
        // TODO: merge with apply arguments

        $keyArgs = [];
        $posArgs = [];

        foreach ($args as $arg) {
            list($key, $value) = $arg;
            $key = $key[1];
            if (empty($key)) {
                $posArgs[] = $value;
            } else {
                $keyArgs[$key] = $value;
            }
        }

        if (!isset($prototype)) {
            return $posArgs;
        }

        $finalArgs = [];
        foreach ($prototype as $i => $names) {
            if (isset($posArgs[$i])) {
                $finalArgs[] = $posArgs[$i];

                continue;
            }

            $set = false;
            foreach ((array) $names as $name) {
                if (isset($keyArgs[$name])) {
                    $finalArgs[] = $keyArgs[$name];
                    $set = true;

                    break;
                }
            }

            if (!$set) {
                $finalArgs[] = null;
            }
        }

        return $finalArgs;
    }

    protected function coerceForExpression($value) {
        if ($color = $this->coerceColor($value)) {
            return $color;
        }

        return $value;
    }

    protected function coerceColor($value) {
        switch ($value[0]) {
            case 'color':
                return $value;
            case 'keyword':
                $name = $value[1];
                if (isset(self::$cssColors[$name])) {
                    $rgba = explode(',', self::$cssColors[$name]);

                    return isset($rgba[3])
                        ? ['color', (int) $rgba[0], (int) $rgba[1], (int) $rgba[2], (int) $rgba[3]]
                        : ['color', (int) $rgba[0], (int) $rgba[1], (int) $rgba[2]];
                }

                return null;
        }

        return null;
    }

    protected function coerceString($value) {
        switch ($value[0]) {
            case 'string':
                return $value;
            case 'keyword':
                return ['string', '', [$value[1]]];
        }

        return null;
    }

    public function assertList($value) {
        if ($value[0] != 'list') {
            $this->throwError('expecting list');
        }

        return $value;
    }

    public function assertColor($value) {
        if ($color = $this->coerceColor($value)) {
            return $color;
        }
        $this->throwError('expecting color');
    }

    public function assertNumber($value) {
        if ($value[0] != 'number') {
            $this->throwError('expecting number');
        }

        return $value[1];
    }

    protected function coercePercent($value) {
        if ($value[0] == 'number') {
            if ($value[2] == '%') {
                return $value[1] / 100;
            }

            return $value[1];
        }

        return 0;
    }

    protected function fixColor($c) {
        // make sure a color's components don't go out of bounds
        foreach (range(1, 3) as $i) {
            if ($c[$i] < 0) {
                $c[$i] = 0;
            }
            if ($c[$i] > 255) {
                $c[$i] = 255;
            }
        }

        return $c;
    }

    public function toHSL($red, $green, $blue) {
        $min = min($red, $green, $blue);
        $max = max($red, $green, $blue);

        $l = $min + $max;

        if ($min == $max) {
            $s = $h = 0;
        } else {
            $d = $max - $min;

            if ($l < 255) {
                $s = $d / $l;
            } else {
                $s = $d / (510 - $l);
            }

            if ($red == $max) {
                $h = 60 * ($green - $blue) / $d;
            } elseif ($green == $max) {
                $h = 60 * ($blue - $red) / $d + 120;
            } elseif ($blue == $max) {
                $h = 60 * ($red - $green) / $d + 240;
            }
        }

        return ['hsl', fmod($h, 360), $s * 100, $l / 5.1];
    }

    public function hueToRGB($m1, $m2, $h) {
        if ($h < 0) {
            $h += 1;
        } elseif ($h > 1) {
            $h -= 1;
        }

        if ($h * 6 < 1) {
            return $m1 + ($m2 - $m1) * $h * 6;
        }

        if ($h * 2 < 1) {
            return $m2;
        }

        if ($h * 3 < 2) {
            return $m1 + ($m2 - $m1) * (2 / 3 - $h) * 6;
        }

        return $m1;
    }

    public function toRGB($hue, $saturation, $lightness) {
        // H from 0 to 360, S and L from 0 to 100
        if ($hue < 0) {
            $hue += 360;
        }

        $h = $hue / 360;
        $s = min(100, max(0, $saturation)) / 100;
        $l = min(100, max(0, $lightness)) / 100;

        $m2 = $l <= 0.5 ? $l * ($s + 1) : $l + $s - $l * $s;
        $m1 = $l * 2 - $m2;

        $r = $this->hueToRGB($m1, $m2, $h + 1 / 3) * 255;
        $g = $this->hueToRGB($m1, $m2, $h) * 255;
        $b = $this->hueToRGB($m1, $m2, $h - 1 / 3) * 255;

        $out = ['color', $r, $g, $b];

        return $out;
    }

    protected function adjustHsl($color, $idx, $amount) {
        $hsl = $this->toHSL($color[1], $color[2], $color[3]);
        $hsl[$idx] += $amount;
        $out = $this->toRGB($hsl[1], $hsl[2], $hsl[3]);
        if (isset($color[4])) {
            $out[4] = $color[4];
        }

        return $out;
    }

    protected function getNormalizedNumbers($args) {
        $unit = null;
        $originalUnit = null;
        $numbers = [];
        foreach ($args as $key => $item) {
            if ('number' != $item[0]) {
                $this->throwError('%s is not a number', $item[0]);
            }
            $number = $this->normalizeNumber($item);

            if (null === $unit) {
                $unit = $number[2];
                $originalUnit = $item[2];
            } elseif ($unit !== $number[2]) {
                $this->throwError('Incompatible units: "%s" and "%s".', $originalUnit, $item[2]);
            }

            $numbers[$key] = $number;
        }

        return $numbers;
    }

    protected function listSeparatorForJoin($list1, $sep) {
        if (!isset($sep)) {
            return $list1[1];
        }
        switch ($this->compileValue($sep)) {
            case 'comma':
                return ',';
            case 'space':
                return '';
            default:
                return $list1[1];
        }
    }

    public function throwError($msg = null) {
        if (func_num_args() > 1) {
            $msg = call_user_func_array('sprintf', func_get_args());
        }

        if ($this->sourcePos >= 0 && isset($this->sourceParser)) {
            $this->sourceParser->throwParseError($msg, $this->sourcePos);
        }

        throw new Exception($msg);
    }
}
