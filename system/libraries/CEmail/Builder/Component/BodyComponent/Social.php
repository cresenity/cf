<?php

use CEmail_Builder_Helper as Helper;

class CEmail_Builder_Component_BodyComponent_Social extends CEmail_Builder_Component_BodyComponent {
    protected static $tagName = 'c-social';
    protected $allowedAttributes = [
        'align' => 'enum(left,right,center)',
        'border-radius' => 'unit(px)',
        'container-background-color' => 'color',
        'color' => 'color',
        'font-family' => 'string',
        'font-size' => 'unit(px)',
        'font-style' => 'string',
        'font-weight' => 'string',
        'icon-size' => 'unit(px,%)',
        'icon-height' => 'unit(px,%)',
        'icon-padding' => 'unit(px,%){1,4}',
        'inner-padding' => 'unit(px,%){1,4}',
        'line-height' => 'unit(px,%,)',
        'mode' => 'enum(horizontal,vertical)',
        'padding-bottom' => 'unit(px,%)',
        'padding-left' => 'unit(px,%)',
        'padding-right' => 'unit(px,%)',
        'padding-top' => 'unit(px,%)',
        'padding' => 'unit(px,%){1,4}',
        'table-layout' => 'enum(auto,fixed)',
        'text-padding' => 'unit(px,%){1,4}',
        'text-decoration' => 'string',
        'vertical-align' => 'enum(top,bottom,middle)',
    ];
    protected $defaultAttributes = [
        'align' => 'center',
        'border-radius' => '3px',
        'color' => '#333333',
        'font-family' => 'Ubuntu, Helvetica, Arial, sans-serif',
        'font-size' => '13px',
        'icon-size' => '20px',
        'inner-padding' => null,
        'line-height' => '22px',
        'mode' => 'horizontal',
        'padding' => '10px 25px',
        'text-decoration' => 'none',
    ];

    public function getStyles() {
        return [
            'tableVertical' => [
                'margin' => '0px',
            ],
        ];
    }

    public function getSocialElementAttributes() {
        $base = [];

        if ($this->getAttribute('inner-padding')) {
            $base['padding'] = $this->getAttribute('inner-padding');
        }

        $prop = [
            'border-radius',
            'color',
            'font-family',
            'font-size',
            'font-weight',
            'font-style',
            'icon-size',
            'icon-height',
            'icon-padding',
            'text-padding',
            'line-height',
            'text-decoration',
        ];

        return carr::reduce($prop, function ($res, $attr) {
            $res[$attr] = $this->getAttribute($attr);
            return $res;
        }, $base);
    }

    public function renderHorizontal() {
        $children = $this->getChildren();
        $tableAttr = [];
        $tableAttr['align'] = $this->getAttribute('align');
        $tableAttr['border'] = '0';
        $tableAttr['cellpadding'] = '0';
        $tableAttr['cellspacing'] = '0';
        $tableAttr['role'] = 'presentation';

        $renderer = function ($component) {
            $tAttr = [];
            $tAttr['align'] = $this->getAttribute('align');
            $tAttr['border'] = '0';
            $tAttr['cellpadding'] = '0';
            $tAttr['cellspacing'] = '0';
            $tAttr['role'] = 'presentation';
            $tAttr['style'] = ['float' => 'none', 'display' => 'inline-table'];
            return '
            <!--[if mso | IE]>
              <td>
            <![endif]-->
              <table' . $component->htmlAttributes($tAttr) . '>
                ' . $component->render() . '
              </table>
            <!--[if mso | IE]>
              </td>
            <![endif]-->
          ';
        };

        return '
     <!--[if mso | IE]>
      <table' . $this->htmlAttributes($tableAttr) . '>
        <tr>
      <![endif]-->
      ' . $this->renderChildren(['attributes' => $this->getSocialElementAttributes(), 'renderer' => $renderer]) . '
      <!--[if mso | IE]>
          </tr>
        </table>
      <![endif]-->
    ';
    }

    public function renderVertical() {
        $children = $this->getChildren();
        $tableAttr = [];
        $tableAttr['border'] = '0';
        $tableAttr['cellpadding'] = '0';
        $tableAttr['cellspacing'] = '0';
        $tableAttr['role'] = 'presentation';
        $tableAttr['style'] = 'tableVertical';

        return '
      <table' . $this->htmlAttributes($tableAttr) . '>
        ' . $this->renderChildren(['attributes' => $this->getSocialElementAttributes()]) . '
      </table>
    ';
    }

    public function render() {
        return $this->getAttribute('mode') === 'horizontal' ? $this->renderHorizontal() : $this->renderVertical();
    }
}
