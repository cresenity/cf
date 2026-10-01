<?php

class CEmail_Builder_Component_BodyComponent_Spacer extends CEmail_Builder_Component_BodyComponent {
    protected static $tagName = 'c-spacer';

    protected static $endingTag = true;

    protected $allowedAttributes = [
        'border' => 'string',
        'border-bottom' => 'string',
        'border-left' => 'string',
        'border-right' => 'string',
        'border-top' => 'string',
        'container-background-color' => 'color',
        'padding-bottom' => 'unit(px,%)',
        'padding-left' => 'unit(px,%)',
        'padding-right' => 'unit(px,%)',
        'padding-top' => 'unit(px,%)',
        'padding' => 'unit(px,%){1,4}',
        'height' => 'unit(px,%)',
    ];

    protected $defaultAttributes = [
        'height' => '20px',
    ];

    public function getStyles() {
        return [
            'div' => [
                'height' => $this->getAttribute('height'),
                'line-height' => $this->getAttribute('height'),
            ],
        ];
    }

    public function render() {
        return '
      <div' . $this->htmlAttributes(['style' => 'div']) . '>&nbsp;</div>
    ';
    }
}
