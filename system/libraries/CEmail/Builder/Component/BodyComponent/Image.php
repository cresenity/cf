<?php

use CEmail_Builder_Helper as Helper;

class CEmail_Builder_Component_BodyComponent_Image extends CEmail_Builder_Component_BodyComponent {
    protected static $tagName = 'c-image';
    protected static $tagOmission = true;
    protected $allowedAttributes = [
        'alt' => 'string',
        'href' => 'string',
        'name' => 'string',
        'src' => 'string',
        'srcset' => 'string',
        'title' => 'string',
        'rel' => 'string',
        'align' => 'enum(left,center,right)',
        'border' => 'string',
        'border-bottom' => 'string',
        'border-left' => 'string',
        'border-right' => 'string',
        'border-top' => 'string',
        'border-radius' => 'unit(px,%){1,4}',
        'container-background-color' => 'color',
        'fluid-on-mobile' => 'boolean',
        'padding' => 'unit(px,%){1,4}',
        'padding-bottom' => 'unit(px,%)',
        'padding-left' => 'unit(px,%)',
        'padding-right' => 'unit(px,%)',
        'padding-top' => 'unit(px,%)',
        'target' => 'string',
        'width' => 'unit(px)',
        'height' => 'unit(px,auto)',
        'max-height' => 'unit(px,%)',
        'font-size' => 'unit(px)',
    ];
    protected $defaultAttributes = [
        'align' => 'center',
        'border' => '0',
        'height' => 'auto',
        'padding' => '10px 25px',
        'target' => '_blank',
        'font-size' => '13px',
    ];

    public function getStyles() {
        $width = $this->getContentWidth();
        $fullWidth = $this->getAttribute('full-width') === 'full-width';

        $widthParserResult = Helper::widthParser($width, ['parseFloatToInt' => false]);
        $unit = carr::get($widthParserResult, 'unit');
        $parsedWidth = carr::get($widthParserResult, 'parsedWidth');

        return [
            'img' => [
                'border' => $this->getAttribute('border'),
                'border-left' => $this->getAttribute('left'),
                'border-right' => $this->getAttribute('right'),
                'border-top' => $this->getAttribute('top'),
                'border-bottom' => $this->getAttribute('bottom'),
                'border-radius' => $this->getAttribute('border-radius'),
                'display' => 'block',
                'outline' => 'none',
                'text-decoration' => 'none',
                'height' => $this->getAttribute('height'),
                'max-height' => $this->getAttribute('max-height'),
                'min-width' => $fullWidth ? '100%' : null,
                'width' => '100%',
                'max-width' => $fullWidth ? '100%' : null,
                'font-size' => $this->getAttribute('font-size'),
            ],
            'td' => [
                'width' => $fullWidth ? null : $parsedWidth . $unit,
            ],
            'table' => [
                'min-width' => $fullWidth ? '100%' : null,
                'max-width' => $fullWidth ? '100%' : null,
                'width' => $fullWidth ? $parsedWidth . $unit : null,
                'border-collapse' => 'collapse',
                'border-spacing' => '0px',
            ],
        ];
    }

    public function getContentWidth() {
        $width = $this->getAttribute('width');
        $boxWidthResult = $this->getBoxWidths();
        $box = carr::get($boxWidthResult, 'box');

        return min([$width, $box]);
    }

    public function renderImage() {
        $height = $this->getAttribute('height');
        $imgAttr = [];
        $imgAttr['alt'] = $this->getAttribute('alt');
        $imgAttr['height'] = $height;
        $imgAttr['src'] = $this->getAttribute('src');
        $imgAttr['srcset'] = $this->getAttribute('srcset');
        $imgAttr['style'] = 'img';
        $imgAttr['title'] = $this->getAttribute('title');
        $imgAttr['width'] = $this->getContentWidth();
        $img = '
      <img' . $this->htmlAttributes($imgAttr) . '/>
    ';
        if (strlen((string) $this->getAttribute('href')) > 0) {
            $aAttr = [];
            $aAttr['href'] = $this->getAttribute('href');
            $aAttr['target'] = $this->getAttribute('target');
            $aAttr['rel'] = $this->getAttribute('rel');
            $aAttr['name'] = $this->getAttribute('name');
            return '
        <a' . $this->htmlAttributes($aAttr) . '>
          ' . $img . '
        </a>
      ';
        }

        return $img;
    }

    public function render() {
        $tableAttr = [];
        $tableAttr['border'] = '0';
        $tableAttr['cellpadding'] = '0';
        $tableAttr['cellspacing'] = '0';
        $tableAttr['role'] = 'presentation';
        $tableAttr['style'] = 'table';
        $tableAttr['class'] = $this->getAttribute('fluid-on-mobile') ? 'c-full-width-mobile' : null;

        return '
      <table' . $this->htmlAttributes($tableAttr) . '>
        <tbody>
          <tr>
            <td' . $this->htmlAttributes(['style' => 'td', 'class' => $this->getAttribute('fluid-on-mobile') ? 'c-full-width-mobile' : null]) . '>
              ' . $this->renderImage() . '
            </td>
          </tr>
        </tbody>
      </table>
    ';
    }
}
