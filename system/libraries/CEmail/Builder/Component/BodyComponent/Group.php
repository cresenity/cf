<?php

use CEmail_Builder_Helper as Helper;

class CEmail_Builder_Component_BodyComponent_Group extends CEmail_Builder_Component_BodyComponent {
    protected static $tagName = 'c-group';

    protected $allowedAttributes = [
        'background-color' => 'color',
        'direction' => 'enum(ltr,rtl)',
        'vertical-align' => 'enum(top,bottom,middle)',
        'width' => 'unit(px,%)',
    ];

    protected $defaultAttributes = [
        'direction' => 'ltr',
    ];

    public function getChildContext() {
        $parentWidth = $this->context->getContainerWidth();
        $parentWidthParserResult = Helper::widthParser($parentWidth, ['parseFloatToInt' => false]);
        $parentParsedWidth = carr::get($parentWidthParserResult, 'parsedWidth');
        $nonRawSiblings = $this->getProp('nonRawSiblings', 0);
        $children = $this->getChildren();

        $paddingSize = intval($this->getShorthandAttrValue('padding', 'left')) + intval($this->getShorthandAttrValue('padding', 'right'));
        $containerWidth = $this->getAttribute('width');
        if (strlen($containerWidth) == 0) {
            if ($nonRawSiblings > 0) {
                $containerWidth = $parentParsedWidth / $nonRawSiblings . 'px';
            }
        }
        $widthParserResult = Helper::widthParser($containerWidth, ['parserFloatToInt' => false]);
        $unit = carr::get($widthParserResult, 'unit');
        $parsedWidth = carr::get($widthParserResult, 'parsedWidth');

        if ($unit === '%') {
            $containerWidth = ($parentParsedWidth * $parsedWidth / 100 - $paddingSize) . 'px';
        } else {
            $containerWidth = ($parentParsedWidth - $paddingSize) . 'px';
        }

        $context = clone $this->context;

        $context->set('containerWidth', $containerWidth);
        $context->set('nonRawSiblings', count($children));

        return $context;
    }

    public function getStyles() {
        return [
            'div' => [
                'font-size' => '0',
                'line-height' => '0',
                'text-align' => 'left',
                'display' => 'inline-block',
                'width' => '100%',
                'direction' => $this->getAttribute('direction'),
                'vertical-align' => $this->getAttribute('vertical-align'),
                'background-color' => $this->getAttribute('background-color'),
            ],
            'tdOutlook' => [
                'vertical-align' => $this->getAttribute('vertical-align'),
                'width' => $this->getWidthAsPixel(),
            ],
        ];
    }

    public function getParsedWidth($toString = false) {
        $nonRawSiblings = $this->getProp('nonRawSiblings');
        $width = $this->getAttribute('width');
        if ($width == null) {
            $width = '100%';
            if ($nonRawSiblings > 0) {
                $width = (100 / $nonRawSiblings) . '%';
            }
        }
        $widthParserResult = Helper::widthParser($width, ['parserFloatToInt' => false]);
        $unit = carr::get($widthParserResult, 'unit');
        $parsedWidth = carr::get($widthParserResult, 'parsedWidth');

        if ($toString) {
            return $parsedWidth . $unit;
        }

        return[
            'unit' => $unit,
            'parsedWidth' => $parsedWidth,
        ];
    }

    public function getWidthAsPixel() {
        $containerWidth = $this->context->getContainerWidth();
        $containerWidthParserResult = Helper::widthParser($containerWidth, ['parseFloatToInt' => false]);
        $containerParsedWidth = carr::get($containerWidthParserResult, 'parsedWidth');
        $widthParserResult = Helper::widthParser($this->getParsedWidth(true), ['parseFloatToInt' => false]);
        $unit = carr::get($widthParserResult, 'unit');
        $parsedWidth = carr::get($widthParserResult, 'parsedWidth');

        if ($unit === '%') {
            return $containerParsedWidth * $parsedWidth / 100 . 'px';
        }

        return $parsedWidth . 'px';
    }

    public function getColumnClass() {
        $className = '';
        $parsedWidthResult = $this->getParsedWidth();
        $parsedWidth = carr::get($parsedWidthResult, 'parsedWidth');
        $unit = carr::get($parsedWidthResult, 'unit');

        switch ($unit) {
            case '%':
                $className = 'c-column-per-' . $parsedWidth;

                break;

            case 'px':
            default:
                $className = 'c-column-px-' . $parsedWidth;

                break;
        }
        // Add className to media queries
        $this->context->addMediaQuery($className, [
            'parsedWidth' => $parsedWidth,
            'unit' => $unit,
        ]);

        return $className;
    }

    public function render() {
        $children = $this->getChildren();
        $nonRawSiblings = $this->getProp('nonRawSiblings');
        $groupWidth = $this->getChildContext()->getContainerWidth();
        $containerWidth = $this->context->getContainerWidth();

        $getElementWidth = function ($width) use ($containerWidth, $nonRawSiblings, $groupWidth) {
            $groupWidthParserResult = Helper::widthParser($groupWidth, ['parseFloatToInt' => false]);
            $groupParsedWidth = carr::get($groupWidthParserResult, 'parsedWidth');

            if (!$width) {
                if ($nonRawSiblings == 0) {
                    return '0px';
                }

                return ($containerWidth / $nonRawSiblings) . 'px';
            }

            $widthParserResult = Helper::widthParser($width, ['parseFloatToInt' => false]);
            $unit = carr::get($widthParserResult, 'unit');
            $parsedWidth = carr::get($widthParserResult, 'parsedWidth');
            if ($unit === '%') {
                if ($groupParsedWidth == 0) {
                    return '0px';
                }

                return (100 * $parsedWidth / $groupParsedWidth) . 'px';
            }

            return $parsedWidth . $unit;
        };
        $classesName = $this->getColumnClass() . ' c-outlook-group-fix';
        if ($this->getAttribute('css-class')) {
            $classesName .= $this->getAttribute('css-class');
        }

        $renderer = function ($component) use ($getElementWidth) {
            if ($component->isRawElement()) {
                return $component->render();
            } else {
                $style = [];
                $style['align'] = $component->getAttribute('align');
                $style['font-size'] = '0px';
                $style['vertical-align'] = $component->getAttribute('vertical-align');
                $style['width'] = call_user_func_array($getElementWidth, [(method_exists($component, 'getWidthAsPixel')) ? $component->getWidthAsPixel() : $component->getAttribute('width')]);

                $tdAttr = [];

                $tdAttr['style'] = $style;

                return '
              <!--[if mso | IE]>
              <td' . $component->htmlAttributes($tdAttr) . '>
              <![endif]-->
                ' . $component->render() . '
              <!--[if mso | IE]>
              </td>
              <![endif]-->
          ';
            }
        };

        return '
      <div' . $this->htmlAttributes(['class' => $classesName, 'style' => 'div']) . '>
        <!--[if mso | IE]>
        <table role="presentation" border="0" cellpadding="0" cellspacing="0">
          <tr>
        <![endif]-->
        ' . $this->renderChildren(['attributes' => ['mobileWidth' => 'mobileWidth'], 'renderer' => $renderer]) . '
        <!--[if mso | IE]>
          </tr>
          </table>
        <![endif]-->
      </div>
    ';
    }
}
