<?php

/**
 * Isi sebuah Mailable: view, teks, markdown, atau HTML jadi (termasuk dari CEmail_Builder).
 */
class CEmail_Mailable_Content {
    /**
     * @var null|string
     */
    public $view;

    /**
     * @var null|string
     */
    public $html;

    /**
     * @var null|string
     */
    public $text;

    /**
     * @var null|string
     */
    public $markdown;

    /**
     * @var null|string
     */
    public $htmlString;

    /**
     * @var array
     */
    public $with = [];

    /**
     * @param array $attributes view, html, text, markdown, htmlString, with
     */
    public function __construct(array $attributes = []) {
        foreach (['view', 'html', 'text', 'markdown', 'htmlString'] as $key) {
            if (isset($attributes[$key])) {
                $this->{$key} = $attributes[$key];
            }
        }
        $this->with = (array) carr::get($attributes, 'with', []);
    }

    /**
     * @param string $view nama view
     *
     * @return $this
     */
    public function view($view) {
        $this->view = $view;

        return $this;
    }

    /**
     * @param string $view nama view HTML
     *
     * @return $this
     */
    public function html($view) {
        $this->html = $view;

        return $this;
    }

    /**
     * @param string $view nama view teks
     *
     * @return $this
     */
    public function text($view) {
        $this->text = $view;

        return $this;
    }

    /**
     * @param string $view nama view markdown
     *
     * @return $this
     */
    public function markdown($view) {
        $this->markdown = $view;

        return $this;
    }

    /**
     * @param string $html HTML jadi, dipakai apa adanya
     *
     * @return $this
     */
    public function htmlString($html) {
        $this->htmlString = $html;

        return $this;
    }

    /**
     * @param array|string $key
     * @param mixed        $value
     *
     * @return $this
     */
    public function with($key, $value = null) {
        if (is_array($key)) {
            $this->with = array_merge($this->with, $key);
        } else {
            $this->with[$key] = $value;
        }

        return $this;
    }

    /**
     * HTML dari pohon CEmail_Builder, dirender saat ini juga.
     *
     * @param CEmail_Builder_RuntimeBuilder $builder
     * @param array                         $renderOptions
     *
     * @return $this
     */
    public function builder(CEmail_Builder_RuntimeBuilder $builder, array $renderOptions = []) {
        return $this->htmlString($builder->render($renderOptions));
    }

    /**
     * HTML dari kerangka CEmail::template(), dirender saat ini juga.
     *
     * @param CEmail_Builder_Template $template
     * @param array                   $renderOptions
     *
     * @return $this
     */
    public function template(CEmail_Builder_Template $template, array $renderOptions = []) {
        return $this->htmlString($template->render($renderOptions));
    }
}
