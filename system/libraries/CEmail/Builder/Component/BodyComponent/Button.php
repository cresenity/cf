<?php

use CEmail_Builder_Helper as Helper;

class CEmail_Builder_Component_BodyComponent_Button extends CEmail_Builder_Component_BodyComponent {
    protected static $endingTag = true;

    protected static $tagName = 'c-button';

    protected $allowedAttributes = [
        'align' => 'enum(left,center,right)',
        'background-color' => 'color',
        'border-bottom' => 'string',
        'border-left' => 'string',
        'border-radius' => 'string',
        'border-right' => 'string',
        'border-top' => 'string',
        'border' => 'string',
        'color' => 'color',
        'container-background-color' => 'color',
        'font-family' => 'string',
        'font-size' => 'unit(px)',
        'font-style' => 'string',
        'font-weight' => 'string',
        'height' => 'unit(px,%)',
        'href' => 'string',
        'name' => 'string',
        'inner-padding' => 'unit(px,%){1,4}',
        'letter-spacing' => 'unitWithNegative(px,%)',
        'line-height' => 'unit(px,%,)',
        'padding-bottom' => 'unit(px,%)',
        'padding-left' => 'unit(px,%)',
        'padding-right' => 'unit(px,%)',
        'padding-top' => 'unit(px,%)',
        'padding' => 'unit(px,%){1,4}',
        'rel' => 'string',
        'target' => 'string',
        'text-decoration' => 'string',
        'text-transform' => 'string',
        'vertical-align' => 'enum(top,bottom,middle)',
        'text-align' => 'enum(left,right,center)',
        'width' => 'unit(px,%)',
    ];

    protected $defaultAttributes = [
        'align' => 'center',
        'background-color' => '#414141',
        'border' => 'none',
        'border-radius' => '3px',
        'color' => '#ffffff',
        'font-family' => 'Ubuntu, Helvetica, Arial, sans-serif',
        'font-size' => '13px',
        'font-weight' => 'normal',
        'inner-padding' => '10px 25px',
        'line-height' => '120%',
        'padding' => '10px 25px',
        'target' => '_blank',
        'text-decoration' => 'none',
        'text-transform' => 'none',
        'vertical-align' => 'middle',
    ];

    public function getStyles() {
        return [
            'table' => [
                'border-collapse' => 'separate',
                'width' => $this->getAttribute('width'),
                'line-height' => '100%',
            ],
            'td' => [
                'border' => $this->getAttribute('border'),
                'border-bottom' => $this->getAttribute('border-bottom'),
                'border-left' => $this->getAttribute('border-left'),
                'border-radius' => $this->getAttribute('border-radius'),
                'border-right' => $this->getAttribute('border-right'),
                'border-top' => $this->getAttribute('border-top'),
                'cursor' => 'auto',
                'font-style' => $this->getAttribute('font-style'),
                'height' => $this->getAttribute('height'),
                'mso-padding-alt' => $this->getAttribute('inner-padding'),
                'text-align' => $this->getAttribute('text-align'),
                'background' => $this->getAttribute('background-color'),
            ],
            'content' => [
                'display' => 'inline-block',
                'width' => $this->calculateAWidth($this->getAttribute('width')),
                'background' => $this->getAttribute('background-color'),
                'color' => $this->getAttribute('color'),
                'font-family' => $this->getAttribute('font-family'),
                'font-size' => $this->getAttribute('font-size'),
                'font-style' => $this->getAttribute('font-style'),
                'font-weight' => $this->getAttribute('font-weight'),
                'line-height' => $this->getAttribute('line-height'),
                'letter-spacing' => $this->getAttribute('letter-spacing'),
                'margin' => '0',
                'text-decoration' => $this->getAttribute('text-decoration'),
                'text-transform' => $this->getAttribute('text-transform'),
                'padding' => $this->getAttribute('inner-padding'),
                'mso-padding-alt' => '0px',
                'border-radius' => $this->getAttribute('border-radius'),
            ],
        ];
    }

    public function calculateAWidth($width) {
        if (!$width) {
            return null;
        }

        $widthParserResult = Helper::widthParser($width);
        $unit = carr::get($widthParserResult, 'unit');
        $parsedWidth = carr::get($widthParserResult, 'parsedWidth');

        // impossible to handle percents because it depends on padding and text width
        if ($unit !== 'px') {
            return null;
        }
        $boxWidthResult = $this->getBoxWidths();
        $borders = carr::get($boxWidthResult, 'borders');

        $innerPaddings = intval($this->getShorthandAttrValue('inner-padding', 'left')) + intval($this->getShorthandAttrValue('inner-padding', 'right'));

        return ($parsedWidth - $innerPaddings - $borders) . 'px';
    }

    public function render() {
        $tag = $this->getAttribute('href') ? 'a' : 'p';
        $tableAttr = [];
        $tableAttr['border'] = '0';
        $tableAttr['cellpadding'] = '0';
        $tableAttr['cellspacing'] = '0';
        $tableAttr['role'] = 'presentation';
        $tableAttr['style'] = 'table';

        $tdAttr = [];
        $tdAttr['align'] = 'center';
        $tdAttr['bgcolor'] = $this->getAttribute('background-color') === 'none' ? null : $this->getAttribute('background-color');
        $tdAttr['role'] = 'presentation';
        $tdAttr['style'] = 'td';
        $tdAttr['valign'] = $this->getAttribute('vertical-align');

        $tagAttr = [];
        $tagAttr['href'] = $this->getAttribute('href');
        $tagAttr['rel'] = $this->getAttribute('rel');
        $tagAttr['name'] = $this->getAttribute('name');
        $tagAttr['style'] = 'content';
        $tagAttr['target'] = $tag === 'a' ? $this->getAttribute('target') : null;

        return '
      <table' . $this->htmlAttributes($tableAttr) . '>
        <tr>
          <td' . $this->htmlAttributes($tdAttr) . '>
            <' . $tag . $this->htmlAttributes($tagAttr) . '>
              ' . $this->getContent() . '
            </' . $tag . '>
          </td>
        </tr>
      </table>
    ';
    }
}
