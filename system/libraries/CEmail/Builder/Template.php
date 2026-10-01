<?php

/**
 * Kerangka email bersama (header logo, section isi, footer) di atas CEmail_Builder.
 *
 * Nilai dibaca dari opsi konstruktor, lalu config `email.template`; subclass boleh meng-override method-nya.
 */
abstract class CEmail_Builder_Template {
    /**
     * @var array
     */
    protected $options;

    /**
     * @var null|string
     */
    protected $title;

    /**
     * @var null|string
     */
    protected $preview;

    /**
     * @var null|CEmail_Builder_RuntimeBuilder
     */
    protected $builder;

    /**
     * @var null|CEmail_Builder_Node
     */
    protected $bodySection;

    /**
     * @param array $options kunci sama dengan config email.template
     */
    public function __construct(array $options = []) {
        $this->options = $options;
    }

    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    protected function option($key, $default = null) {
        if (array_key_exists($key, $this->options)) {
            return $this->options[$key];
        }
        $value = CF::config('email.template.' . $key);

        return $value === null ? $default : $value;
    }

    /**
     * @return null|string
     */
    public function logoUrl() {
        $logoUrl = $this->option('logo_url');

        return strlen((string) $logoUrl) > 0 ? $logoUrl : null;
    }

    /**
     * @return string
     */
    public function appName() {
        $name = $this->option('app_name');
        if (strlen((string) $name) == 0) {
            $name = CF::config('app.name');
        }
        if (strlen((string) $name) == 0) {
            $name = CF::appCode();
        }

        return (string) $name;
    }

    /**
     * @return string
     */
    public function primaryColor() {
        return $this->option('primary_color', '#1a347b');
    }

    /**
     * @return string
     */
    public function backgroundColor() {
        return $this->option('background_color', '#c4c4c4');
    }

    /**
     * @return string
     */
    public function textColor() {
        return $this->option('text_color', '#555555');
    }

    /**
     * @return string
     */
    public function fontFamily() {
        return $this->option('font_family', 'Arial, sans-serif');
    }

    /**
     * @return string
     */
    public function width() {
        return $this->option('width', '650px');
    }

    /**
     * Teks HTML footer; {year} dan {app_name} diganti.
     *
     * @return string
     */
    public function footerText() {
        $text = $this->option('footer_text');
        if (strlen((string) $text) == 0) {
            $text = 'Copyright &copy; {year} {app_name}';
        }

        return str_replace(['{year}', '{app_name}'], [date('Y'), $this->escape($this->appName())], $text);
    }

    /**
     * @return bool
     */
    public function showHeader() {
        return (bool) $this->option('show_header', true);
    }

    /**
     * @return bool
     */
    public function showFooter() {
        return (bool) $this->option('show_footer', true);
    }

    /**
     * @param string $title
     *
     * @return $this
     */
    public function title($title) {
        $this->title = $title;

        return $this;
    }

    /**
     * @param string $preview teks pratinjau (preheader) di daftar inbox
     *
     * @return $this
     */
    public function preview($preview) {
        $this->preview = $preview;

        return $this;
    }

    /**
     * Susun ulang pohon builder; konten yang sudah ditambahkan ke bodySection() sebelumnya hilang.
     *
     * @return CEmail_Builder_RuntimeBuilder
     */
    public function build() {
        $builder = CEmail::builder()->createRuntimeBuilder();

        $head = $builder->addHead();
        $head->addTitle($this->title !== null ? $this->title : $this->appName());
        if ($this->preview !== null) {
            $head->addPreview($this->preview);
        }
        $defaults = $head->addHeadAttributes();
        $defaults->addAll()->setFontFamily($this->fontFamily());
        $defaults->addNode('c-text')->setColor($this->textColor());

        $body = $builder->addBody()
            ->setBackgroundColor($this->backgroundColor())
            ->setPadding('30px 0px')
            ->setWidth($this->width());

        if ($this->showHeader()) {
            $this->buildHeader($body);
        }

        $this->bodySection = $body->addSection()
            ->setBackgroundColor('#ffffff')
            ->setPadding('0 0 24px 0')
            ->setTextAlign('left');

        if ($this->showFooter()) {
            $this->buildFooter($body);
        }

        $this->builder = $builder;

        return $builder;
    }

    /**
     * @param CEmail_Builder_Node $body
     *
     * @return void
     */
    protected function buildHeader(CEmail_Builder_Node $body) {
        $column = $body->addSection()
            ->setBackgroundColor('#ffffff')
            ->setPadding('20px 0')
            ->setTextAlign('center')
            ->addColumn();

        $logoUrl = $this->logoUrl();
        if ($logoUrl !== null) {
            $column->addImage()
                ->setAlign('center')
                ->setSrc($logoUrl)
                ->setAlt($this->appName())
                ->setWidth('128px');
        } else {
            $column->addText()
                ->setAlign('center')
                ->setFontSize('22px')
                ->setFontWeight('bold')
                ->setColor($this->primaryColor())
                ->add($this->escape($this->appName()));
        }
        $column->addDivider()->setBorderColor($this->primaryColor());
    }

    /**
     * @param CEmail_Builder_Node $body
     *
     * @return void
     */
    protected function buildFooter(CEmail_Builder_Node $body) {
        $body->addSection()
            ->setBackgroundColor($this->primaryColor())
            ->setPadding('20px 0')
            ->setTextAlign('center')
            ->addColumn()
            ->addText()
            ->setColor('#ffffff')
            ->setAlign('center')
            ->setFontSize('13px')
            ->setLineHeight('22px')
            ->setPadding('10px 25px')
            ->add($this->footerText());
    }

    /**
     * @return CEmail_Builder_RuntimeBuilder
     */
    public function builder() {
        if ($this->builder === null) {
            $this->build();
        }

        return $this->builder;
    }

    /**
     * Section putih tempat konten email ditambahkan: bodySection()->addColumn()->addText()->add(...).
     *
     * @return CEmail_Builder_Node
     */
    public function bodySection() {
        if ($this->bodySection === null) {
            $this->build();
        }

        return $this->bodySection;
    }

    /**
     * @param array $options opsi CEmail_Builder_RuntimeBuilder::render()
     *
     * @return string
     */
    public function render(array $options = []) {
        return $this->builder()->render($options);
    }

    /**
     * @param string $text
     *
     * @return string
     */
    protected function escape($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false);
    }
}
