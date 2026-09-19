<?php

use CEmail_Builder_Helper as Helper;

class CEmail_Builder_Component_BodyComponent_Divider extends CEmail_Builder_Component_BodyComponent {
    protected static $tagName = 'c-divider';
    protected static $endingTag = true;
    protected static $tagOmission = true;
    protected $allowedAttributes = [
        'border-color' => 'color',
        'border-style' => 'string',
        'border-width' => 'unit(px)',
        'container-background-color' => 'color',
        'padding' => 'unit(px,%){1,4}',
        'padding-bottom' => 'unit(px,%)',
        'padding-left' => 'unit(px,%)',
        'padding-right' => 'unit(px,%)',
        'padding-top' => 'unit(px,%)',
        'width' => 'unit(px,%)',
    ];
    protected $defaultAttributes = [
        'border-color' => '#000000',
        'border-style' => 'solid',
        'border-width' => '4px',
        'padding' => '10px 25px',
        'width' => '100%',
    ];

    public function getStyles() {
        $borderTop = implode(' ', carr::map(['style', 'width', 'color'], function ($attr) {
            return $this->getAttribute('border-' . $attr);
        }));
        $p = [
            'border-top' => $borderTop,
            'font-size' => '1px',
            'margin' => '0px auto',
            'width' => $this->getAttribute('width'),
        ];

        $outlook = $p;
        $outlook['width'] = $this->getOutlookWidth();

        return [
            'p' => $p,
            'outlook' => $outlook,
        ];
    }

    public function getOutlookWidth() {
        $containerWidth = $this->context->getContainerWidth();
        $paddingSize = intval($this->getShorthandAttrValue('padding', 'left')) + intval($this->getShorthandAttrValue('padding', 'right'));

        $width = $this->getAttribute('width');

        $widthParserResult = Helper::widthParser($width);
        $unit = carr::get($widthParserResult, 'unit');
        $parsedWidth = carr::get($widthParserResult, 'parsedWidth');
        switch ($unit) {
            case '%':
                return ((intval($containerWidth) * $parsedWidth / 100) - $paddingSize) . 'px';
            case 'px':
                return $width;
            default:
                return (intval($containerWidth) - $paddingSize) . 'px';
        }
    }

    public function renderAfter() {
        $attr = [];
        $attr['align'] = 'center';
        $attr['border'] = '0';
        $attr['cellpadding'] = '0';
        $attr['cellspacing'] = '0';
        $attr['style'] = 'outlook';
        $attr['role'] = 'presentation';

        $attr['width'] = $this->getOutlookWidth();
        return '
      <!--[if mso | IE]>
        <table' . $this->htmlAttributes($attr) . '>
          <tr>
            <td style="height:0;line-height:0;">
              &nbsp;
            </td>
          </tr>
        </table>
      <![endif]-->
    ';
    }

    public function render() {
        return '
      <p' . $this->htmlAttributes(['style' => 'p']) . '>
      </p>
      ' . $this->renderAfter() . '
    ';
    }
}
