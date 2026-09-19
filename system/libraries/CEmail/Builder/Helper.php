<?php

class CEmail_Builder_Helper {
    public static function widthParser($width, $options = []) {
        $parseFloatToInt = carr::get($options, 'parseFloatToInt', true);

        $unitRegex = '/[\d.,]*(\D*)$/';
        $widthUnit = 'px';
        if (preg_match($unitRegex, $width, $matches)) {
            $widthUnit = $matches[1];
        }
        $parsedWidth = floatval($width);

        switch ($widthUnit) {
            case '%':
                $parsedWidth = $parseFloatToInt ? floor($parsedWidth) : $parsedWidth;

                break;
            case 'px':
            default:
                $parsedWidth = floor($parsedWidth);

                break;
        }
        if (strlen($widthUnit) == 0) {
            $widthUnit = 'px';
        }

        return [
            'parsedWidth' => static::formatNumber($parsedWidth),
            'unit' => $widthUnit,
        ];
    }

    /**
     * Bilangan bulat dikembalikan sebagai int; pecahan dibulatkan ke 12 digit agar 600/3*3 tetap 600 dan
     * tidak ada ekor floating point di CSS.
     *
     * @param float|int $number
     *
     * @return float|int
     */
    public static function formatNumber($number) {
        $rounded = round((float) $number, 12);

        return $rounded == (int) $rounded ? (int) $rounded : $rounded;
    }

    public static function suffixCssClasses($classes, $suffix) {
        if ($classes != null) {
            return implode(' ', carr::map(explode(' ', $classes), function ($c) use ($suffix) {
                return $c . '-' . $suffix;
            }));
        }

        return '';
    }

    public static function jsonToXML($tagName, $attributes, $children, $content) {
        $subNode = $content;
        if (is_array($children) && count($children) > 0) {
            $subNode = implode("\n", carr::map($children, function ($child) {
                //recursive here
                return CEmail_Builder_Helper::jsonToXML($child->getTagName(), $child->getAttributes(), $child->getChildren(), $child->getContent());
            }));
        }

        $attributesString = carr::reduce($attributes, function ($output, $v, $name) {
            if ($v != null && strlen($v) > 0) {
                return $output . ' ' . $name . '="' . $v . '"';
            }

            return $output;
        }, '');

        return '<' . $tagName . $attributesString . '>' . $subNode . '</' . $tagName . '>';
    }

    /**
     * @param array $attributes
     * @param array $allowedAttributes
     *
     * @return array
     */
    public static function formatAttributes($attributes, $allowedAttributes) {
        return carr::reduce($attributes, function ($acc, $val, $attrName) use ($allowedAttributes) {
            if (is_array($allowedAttributes) && isset($allowedAttributes[$attrName])) {
                $typeClass = CEmail::builder()->determineTypeAdapter($allowedAttributes[$attrName]);
                if ($typeClass) {
                    $typeAdapter = new $typeClass($allowedAttributes[$attrName], $val);

                    return array_merge($acc, [$attrName => $typeAdapter->getValue()]);
                }
            }

            return array_merge($acc, [$attrName => $val]);
        }, []);
    }

    public static function renderStyle($styles) {
        if (!is_array($styles)) {
            $styles = [$styles];
        }

        return carr::reduce($styles, function ($output, $value, $name) {
            if ($value !== null && strlen($value) > 0) {
                return $output . $name . ':' . $value . ';';
            }

            return $output;
        }, '');
    }

    public static function conditionalTag($content, $negation = false) {
        $startConditionalTag = '<!--[if mso | IE]>';
        $startNegationConditionalTag = '<!--[if !mso | IE]><!-->';
        $endConditionalTag = '<![endif]-->';
        $endNegationConditionalTag = '<!--<![endif]-->';

        return ($negation ? $startNegationConditionalTag : $startConditionalTag) . $content . ($negation ? $endNegationConditionalTag : $endConditionalTag);
    }

    public static function msoConditionalTag($content, $negation = false) {
        $startMsoConditionalTag = '<!--[if mso]>';
        $startMsoNegationConditionalTag = '<!--[if !mso><!-->';
        $endConditionalTag = '<![endif]-->';
        $endNegationConditionalTag = '<!--<![endif]-->';

        return ($negation ? $startMsoNegationConditionalTag : $startMsoConditionalTag) . $content . ($negation ? $endNegationConditionalTag : $endConditionalTag);
    }

    public static function shorthandParser($cssValue, $direction) {
        $splittedCssValue = explode(' ', $cssValue);
        $directions = [];
        switch (count($splittedCssValue)) {
            case 2:
                $directions = ['top' => 0, 'bottom' => 0, 'left' => 1, 'right' => 1];

                break;
            case 3:
                $directions = ['top' => 0, 'left' => 0, 'right' => 1, 'bottom' => 2];

                break;
            case 4:
                $directions = ['top' => 0, 'right' => 1, 'bottom' => 2, 'left' => 3];

                break;
            case 1:
            default:
                return $cssValue;
        }

        return carr::get($splittedCssValue, $directions[$direction], 0);
    }

    public static function borderParser($border) {
        if (preg_match('/(?:(?:^| )(\d+))/', $border, $matches)) {
            return carr::get($matches, 1);
        }

        return 0;
    }
}
