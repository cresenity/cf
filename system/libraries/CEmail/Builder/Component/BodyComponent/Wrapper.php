<?php

use CEmail_Builder_Helper as Helper;

class CEmail_Builder_Component_BodyComponent_Wrapper extends CEmail_Builder_Component_BodyComponent_Section {
    protected static $tagName = 'c-wrapper';

    public function getChildContext() {
        $context = clone $this->context;
        $boxWidths = $this->getBoxWidths();
        $context->set('containerWidth', carr::get($boxWidths, 'box') . 'px');

        return $context;
    }

    public function renderWrappedChildren() {
        $containerWidth = $this->context ? $this->context->getContainerWidth() : '100%';
        $renderer = function ($component) use ($containerWidth) {
            if ($component->isRawElement()) {
                return $component->render();
            }

            $attrOptions = [];
            $attrOptions['align'] = $component->getAttribute('align');
            $attrOptions['class'] = Helper::suffixCssClasses($component->getAttribute('css-class'), 'outlook');
            $attrOptions['width'] = $containerWidth;

            return '
          <!--[if mso | IE]>
            <tr>
              <td' . $component->htmlAttributes($attrOptions) . '>
          <![endif]-->
            ' . $component->render() . '
          <!--[if mso | IE]>
              </td>
            </tr>
          <![endif]-->
    ';
        };

        return $this->renderChildren(['renderer' => $renderer]);
    }
}
