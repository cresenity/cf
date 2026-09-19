<?php

use CEmail_Builder_Helper as Helper;

class CEmail_Builder_Component_BodyComponent_Column extends CEmail_Builder_Component_BodyComponent {
    protected static $tagName = 'c-column';
    protected $allowedAttributes = [
        'background-color' => 'color',
        'border' => 'string',
        'border-bottom' => 'string',
        'border-left' => 'string',
        'border-radius' => 'unit(px,%){1,4}',
        'border-right' => 'string',
        'border-top' => 'string',
        'direction' => 'enum(ltr,rtl)',
        'padding-bottom' => 'unit(px,%)',
        'padding-left' => 'unit(px,%)',
        'padding-right' => 'unit(px,%)',
        'padding-top' => 'unit(px,%)',
        'padding' => 'unit(px,%){1,4}',
        'vertical-align' => 'enum(top,bottom,middle)',
        'width' => 'unit(px,%)',
    ];
    protected $defaultAttributes = [
        'direction' => 'ltr',
        'vertical-align' => 'top',
    ];

    public function getStyles() {
        $tableStyle = [
            'background-color' => $this->getAttribute('background-color'),
            'border' => $this->getAttribute('border'),
            'border-bottom' => $this->getAttribute('border-bottom'),
            'border-left' => $this->getAttribute('border-left'),
            'border-radius' => $this->getAttribute('border-radius'),
            'border-right' => $this->getAttribute('border-right'),
            'border-top' => $this->getAttribute('border-top'),
            'vertical-align' => $this->getAttribute('vertical-align'),
        ];

        return [
            'div' => [
                'font-size' => '0px',
                'text-align' => 'left',
                'direction' => $this->getAttribute('direction'),
                'display' => 'inline-block',
                'vertical-align' => $this->getAttribute('vertical-align'),
                'width' => $this->getMobileWidth(),
            ],
            'table' => $this->hasGutter() ? [] : $tableStyle,
            'tdOutlook' => [
                'vertical-align' => $this->getAttribute('vertical-align'),
                'width' => $this->getWidthAsPixel(),
            ],
            'gutter' => array_merge($tableStyle, [
                'padding' => $this->getAttribute('padding'),
                'padding-top' => $this->getAttribute('padding-top'),
                'padding-right' => $this->getAttribute('padding-right'),
                'padding-bottom' => $this->getAttribute('padding-bottom'),
                'padding-left' => $this->getAttribute('padding-left'),
            ]),
        ];
    }

    public function getMobileWidth() {
        $containerWidth = $this->context->getContainerWidth();
        $nonRawSiblings = $this->getProp('nonRawSiblings', 0);
        $width = $this->getAttribute('width');
        $mobileWidth = $this->getAttribute('mobileWidth');

        if ($mobileWidth !== 'mobileWidth') {
            return '100%';
        } elseif (strlen($width) == 0) {
            $width = '100%';
            if ($nonRawSiblings > 0) {
                $width = (100 / $nonRawSiblings) . '%';
            }
            return $width;
        }
        $widthParserResult = Helper::widthParser($width, ['parseFloatToInt' => false]);
        $unit = carr::get($widthParserResult, 'unit');
        $parsedWidth = carr::get($widthParserResult, 'parsedWidth');

        switch ($unit) {
            case '%':
                return $width;
            case 'px':
            default:
                if (strlen((string) $containerWidth) == 0 || intval($containerWidth) == 0) {
                    return $parsedWidth . 'px';
                }

                return ($parsedWidth / intval($containerWidth)) * 100 . '%';
        }
    }

    public function getWidthAsPixel() {
        $containerWidth = $this->context->getContainerWidth();

        $widthParserResult = Helper::widthParser($this->getParsedWidth(true), ['parseFloatToInt' => false]);
        $unit = carr::get($widthParserResult, 'unit');
        $parsedWidth = carr::get($widthParserResult, 'parsedWidth');

        if ($unit === '%') {
            return intval($containerWidth) * $parsedWidth / 100 . 'px';
        }
        return $parsedWidth . 'px';
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

    public function getColumnClass() {
        $className = '';
        $parsedWidthResult = $this->getParsedWidth();
        $parsedWidth = carr::get($parsedWidthResult, 'parsedWidth');
        $unit = carr::get($parsedWidthResult, 'unit');

        $formattedClassNb = str_replace('.', '-', $parsedWidth);

        switch ($unit) {
            case '%':
                $className = 'c-column-per-' . $formattedClassNb;
                break;

            case 'px':
            default:
                $className = 'c-column-px-' . $formattedClassNb;
                break;
        }

        // Add className to media queries
        $this->context->addMediaQuery($className, [
            'parsedWidth' => $parsedWidth,
            'unit' => $unit,
        ]);

        return $className;
    }

    public function hasGutter() {
        return carr::some([
            'padding',
            'padding-bottom',
            'padding-left',
            'padding-right',
            'padding-top',
        ], function ($attr) {
            return $this->getAttribute($attr) != null;
        });
    }

    public function renderGutter() {
        $tableAttr = [];
        $tableAttr['border'] = '0';
        $tableAttr['cellpadding'] = '0';
        $tableAttr['cellspacing'] = '0';
        $tableAttr['role'] = 'presentation';
        $tableAttr['width'] = '100%';
        return '
      <table' . $this->htmlAttributes($tableAttr) . '>
        <tbody>
          <tr>
            <td' . $this->htmlAttributes(['style' => 'gutter']) . '>
              ' . $this->renderColumn() . '
            </td>
          </tr>
        </tbody>
      </table>
    ';
    }

    public function renderColumn() {
        $children = $this->getChildren();
        $tableAttr = [];
        $tableAttr['border'] = '0';
        $tableAttr['cellpadding'] = '0';
        $tableAttr['cellspacing'] = '0';
        $tableAttr['role'] = 'presentation';
        $tableAttr['style'] = 'table';
        $tableAttr['width'] = '100%';

        $renderer = function ($component) {
            if ($component->isRawElement()) {
                return $component->render();
            } else {
                $style = [];
                $style['background'] = $component->getAttribute('container-background-color');
                $style['font-size'] = '0px';
                $style['padding'] = $component->getAttribute('padding');
                $style['padding-bottom'] = $component->getAttribute('padding-bottom');
                $style['padding-left'] = $component->getAttribute('padding-left');
                $style['padding-right'] = $component->getAttribute('padding-right');
                $style['padding-top'] = $component->getAttribute('padding-top');
                $style['word-break'] = 'break-word';
                $tdAttr = [];
                $tdAttr['align'] = $component->getAttribute('align');
                $tdAttr['vertical-align'] = $component->getAttribute('vertical-align');
                $tdAttr['class'] = $component->getAttribute('css-class');
                $tdAttr['style'] = $style;
                return '
            <tr>
              <td' . $component->htmlAttributes($tdAttr) . '>
                ' . $component->render() . '
              </td>
            </tr>
          ';
            }
        };

        return '
      <table' . $this->htmlAttributes($tableAttr) . '>
        ' . $this->renderChildren(['renderer' => $renderer]) . '
      </table>
    ';
    }

    public function render() {
        $classesName = $this->getColumnClass() . ' c-outlook-group-fix';

        if ($this->getAttribute('css-class')) {
            $classesName .= ' ' . $this->getAttribute('css-class');
        }

        return '
      <div' . $this->htmlAttributes(['class' => $classesName, 'style' => 'div']) . '>
        ' . ($this->hasGutter() ? $this->renderGutter() : $this->renderColumn()) . '
      </div>
    ';
    }
}
