<?php

use CEmail_Builder_Helper as Helper;

class CEmail_Builder_Component_BodyComponent_Section extends CEmail_Builder_Component_BodyComponent {
    protected static $tagName = 'c-section';
    protected $allowedAttributes = [
        'background-color' => 'color',
        'background-url' => 'string',
        'background-repeat' => 'enum(repeat,no-repeat)',
        'background-size' => 'string',
        'border' => 'string',
        'border-bottom' => 'string',
        'border-left' => 'string',
        'border-radius' => 'string',
        'border-right' => 'string',
        'border-top' => 'string',
        'direction' => 'enum(ltr,rtl)',
        'full-width' => 'enum(full-width)',
        'padding' => 'unit(px,%){1,4}',
        'padding-top' => 'unit(px,%)',
        'padding-bottom' => 'unit(px,%)',
        'padding-left' => 'unit(px,%)',
        'padding-right' => 'unit(px,%)',
        'text-align' => 'enum(left,center,right)',
        'text-padding' => 'unit(px,%){1,4}',
    ];
    protected $defaultAttributes = [
        'background-repeat' => 'repeat',
        'background-size' => 'auto',
        'direction' => 'ltr',
        'padding' => '20px 0',
        'text-align' => 'center',
        'text-padding' => '4px 4px 4px 0',
    ];

    public function getStyles() {
        $parentStyles = parent::getStyles();
        $containerWidth = $this->context->getContainerWidth();

        $fullWidth = $this->isFullWidth();

        $background = $this->hasBackground() ? [
            'background' => $this->getBackground(),
            'background-position' => 'top center',
            'background-repeat' => $this->getAttribute('background-repeat'),
            'background-size' => $this->getAttribute('background-size'),
        ] : ['background' => $this->getAttribute('background-color'), 'background-color' => $this->getAttribute('background-color')];
        return [
            'tableFullWidth' => array_merge(($fullWidth ? $background : []), ['width' => '100%', 'border-radius' => $this->getAttribute('border-radius')]),
            'table' => array_merge(($fullWidth ? [] : $background), ['width' => '100%', 'border-radius' => $this->getAttribute('border-radius')]),
            'td' => [
                'border' => $this->getAttribute('border'),
                'border-bottom' => $this->getAttribute('border-bottom'),
                'border-left' => $this->getAttribute('border-left'),
                'border-right' => $this->getAttribute('border-right'),
                'border-top' => $this->getAttribute('border-top'),
                'direction' => $this->getAttribute('direction'),
                'font-size' => '0px',
                'padding' => $this->getAttribute('padding'),
                'padding-bottom' => $this->getAttribute('padding-bottom'),
                'padding-left' => $this->getAttribute('padding-left'),
                'padding-right' => $this->getAttribute('padding-right'),
                'padding-top' => $this->getAttribute('padding-top'),
                'text-align' => $this->getAttribute('text-align'),
            ],
            'div' => array_merge(($fullWidth ? [] : $background), ['margin' => '0px auto', 'border-radius' => $this->getAttribute('border-radius'), 'max-width' => $containerWidth]),
            'innerDiv' => [
                'line-height' => '0',
                'font-size' => '0'
            ]
        ];
    }

    public function getBackground() {
        $arrBackground = [];
        $arrBackground[] = $this->getAttribute('background-color');
        if ($this->hasBackground()) {
            $arrBackground = array_merge($arrBackground, ['url(' . $this->getAttribute('background-url') . ')', 'top center / ' . $this->getAttribute('background-size'), $this->getAttribute('background-repeat')]);
        }
        return trim(carr::reduce($arrBackground, function ($output, $v, $key) {
            if ($v != null && strlen($v) > 0) {
                return $output . ' ' . $v;
            }
            return $output;
        }, ''));
    }

    public function hasBackground() {
        return $this->getAttribute('background-url') != null;
    }

    public function isFullWidth() {
        return $this->getAttribute('full-width') === 'full-width';
    }

    public function renderBefore() {
        $containerWidth = $this->context ? $this->context->getContainerWidth() : '100%';
        $attr = [];
        $attr['align'] = 'center';
        $attr['border'] = '0';
        $attr['cellpadding'] = '0';
        $attr['cellspacing'] = '0';
        $attr['class'] = Helper::suffixCssClasses($this->getAttribute('css-class'), 'outlook');
        $attr['style'] = ['width' => $containerWidth];
        $attr['width'] = $containerWidth;

        return '
      <!--[if mso | IE]>
      <table' . $this->htmlAttributes($attr) . '>
        <tr>
          <td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;">
      <![endif]-->
    ';
    }

    public function renderAfter() {
        return '
      <!--[if mso | IE]>
          </td>
        </tr>
      </table>
      <![endif]-->
    ';
    }

    public function renderWrappedChildren() {
        $children = $this->getChildren();
        $renderer = function ($component) {
            if ($component->isRawElement()) {
                return $component->render();
            } else {
                $attrOptions = [];
                $attrOptions['align'] = $component->getAttribute('align');
                $attrOptions['class'] = Helper::suffixCssClasses($component->getAttribute('css-class'), 'outlook');
                $attrOptions['style'] = 'tdOutlook';
                return '
          <!--[if mso | IE]>
            <td' . $component->htmlAttributes($attrOptions) . '>
          <![endif]-->
            ' . $component->render() . '
          <!--[if mso | IE]>
            </td>
          <![endif]-->
    ';
            }
        };

        return '
      <!--[if mso | IE]>
        <tr>
      <![endif]-->
      ' . $this->renderChildren(['renderer' => $renderer]) . '
      <!--[if mso | IE]>
        </tr>
      <![endif]-->
    ';
    }

    public function renderWithBackground($content) {
        $fullWidth = $this->isFullWidth();
        $containerWidth = $this->context ? $this->context->getContainerWidth() : '100%';

        $vRectAttr = [];
        $vRectAttr['style'] = $fullWidth ? ['mso-width-percent' => '1000'] : ['width' => $containerWidth];
        $vRectAttr['xmlns:v'] = 'urn:schemas-microsoft-com:vml';
        $vRectAttr['fill'] = 'true';
        $vRectAttr['stroke'] = 'false';

        $vFillAttr = [];
        $vFillAttr['origin'] = '0.5, 0';
        $vFillAttr['position'] = '0.5, 0';
        $vFillAttr['src'] = $this->getAttribute('background-url');
        $vFillAttr['color'] = $this->getAttribute('background-color');
        $vFillAttr['type'] = 'tile';
        return '
      <!--[if mso | IE]>
        <v:rect' . $this->htmlAttributes($vRectAttr) . '>
        <v:fill' . $this->htmlAttributes($vFillAttr) . '/>
        <v:textbox style="mso-fit-shape-to-text:true" inset="0,0,0,0">
      <![endif]-->
          ' . $content . '
        <!--[if mso | IE]>
        </v:textbox>
      </v:rect>
    <![endif]-->
    ';
    }

    public function renderSection() {
        $hasBackground = $this->hasBackground();
        $innerDivOpen = '';
        $innerDivClose = '';
        if ($hasBackground) {
            $innerDivOpen = '<div' . $this->htmlAttributes(['style' => 'innerDiv']) . '>';
            $innerDivClose = '</div>';
        }
        $tableAttributes = [];
        $tableAttributes['align'] = 'center';
        $tableAttributes['background'] = $this->isFullWidth() ? null : $this->getAttribute('background-url');
        $tableAttributes['border'] = '0';
        $tableAttributes['cellpadding'] = '0';
        $tableAttributes['cellspacing'] = '0';
        $tableAttributes['role'] = 'presentation';
        $tableAttributes['style'] = 'table';

        return '
      <div' . $this->htmlAttributes(['class' => $this->isFullWidth() ? null : $this->getAttribute('css-class'), 'style' => 'div']) . '>
        ' . $innerDivOpen . '
        <table' . $this->htmlAttributes($tableAttributes) . '>
          <tbody>
            <tr>
              <td' . $this->htmlAttributes(['style' => 'td']) . '>
                <!--[if mso | IE]>
                  <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <![endif]-->
                  ' . $this->renderWrappedChildren() . '
                <!--[if mso | IE]>
                  </table>
                <![endif]-->
              </td>
            </tr>
          </tbody>
        </table>
        ' . $innerDivClose . '
      </div>
    ';
    }

    public function renderFullWidth() {
        $content = $this->hasBackground() ? $this->renderWithBackground($this->renderBefore() . $this->renderSection() . $this->renderAfter()) : $this->renderBefore() . $this->renderSection() . $this->renderAfter();

        $optionsAttributes = [];
        $optionsAttributes['align'] = 'center';
        $optionsAttributes['class'] = $this->getAttribute('css-class');
        $optionsAttributes['background'] = $this->getAttribute('background-url');
        $optionsAttributes['border'] = 0;
        $optionsAttributes['cellpadding'] = 0;
        $optionsAttributes['cellspacing'] = 0;
        $optionsAttributes['role'] = 'presentation';
        $optionsAttributes['style'] = 'tableFullWidth';
        return '
      <table' . $this->htmlAttributes($optionsAttributes) . '>
        <tbody>
          <tr>
            <td>
              ' . $content . '
            </td>
          </tr>
        </tbody>
      </table>
    ';
    }

    public function renderSimple() {
        $section = $this->renderSection();

        return $this->renderBefore() . ($this->hasBackground() ? $this->renderWithBackground($section) : $section) . $this->renderAfter();
    }

    public function render() {
        return $this->isFullWidth() ? $this->renderFullWidth() : $this->renderSimple();
    }
}
