<?php

class CReport_Builder_Report implements CReport_Builder_Contract_JrXmlElementInterface {
    use CReport_Builder_Trait_HasChildrenElementTrait;
    use CReport_Builder_Trait_Property_FontPropertyTrait;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var float
     */
    protected $pageWidth;

    /**
     * @var float
     */
    protected $pageHeight;

    /**
     * @var float
     */
    protected $columnWidth;

    /**
     * @var float
     */
    protected $leftMargin;

    /**
     * @var float
     */
    protected $rightMargin;

    /**
     * @var float
     */
    protected $topMargin;

    /**
     * @var float
     */
    protected $bottomMargin;

    /**
     * @var CReport_Builder_ElementAbstract[]
     */
    protected $children;

    /**
     * @var string
     */
    protected $orientation;

    /**
     * @var CReport_Builder_Object_Font
     */
    protected $defaultFont;

    /**
     * @param null|string $name
     */
    public function __construct($name = null) {
        $this->name = $name ?: 'CReport';
        $paperSize = CReport_Paper::$pageFormats['A4'];
        $this->children = [];
        $this->pageWidth = $paperSize[0];
        $this->pageHeight = $paperSize[1];
        $this->leftMargin = 20;
        $this->topMargin = 20;
        $this->bottomMargin = 20;
        $this->rightMargin = 20;
        $this->font = new CReport_Builder_Object_Font();
        $this->defaultFont = new CReport_Builder_Object_Font();
    }

    /**
     * @param SimpleXMLElement $xml
     *
     * @return static
     */
    public static function fromXml(SimpleXMLElement $xml) {
        $report = new self((string) $xml['name']);
        if ($xml['pageWidth']) {
            $report->setPageWidth((float) $xml['pageWidth']);
        }
        if ($xml['pageHeight']) {
            $report->setPageHeight((float) $xml['pageHeight']);
        }
        if ($xml['columnWidth']) {
            $report->setColumnWidth((float) $xml['columnWidth']);
        }
        if ($xml['leftMargin']) {
            $report->setLeftMargin((float) $xml['leftMargin']);
        }
        if ($xml['rightMargin']) {
            $report->setRightMargin((float) $xml['rightMargin']);
        }
        if ($xml['topMargin']) {
            $report->setTopMargin((float) $xml['topMargin']);
        }
        if ($xml['bottomMargin']) {
            $report->setBottomMargin((float) $xml['bottomMargin']);
        }
        if ($xml['orientation']) {
            $report->setOrientation(cstr::lower((string) $xml['orientation']) == CReport::ORIENTATION_LANDSCAPE ? CReport::ORIENTATION_LANDSCAPE : CReport::ORIENTATION_PORTRAIT);
        }
        foreach ($xml as $tag => $xmlElement) {
            if ($tag == 'defaultFont') {
                $report->setDefaultFont(CReport_Builder_Object_Font::fromXml($xmlElement));
                $report->setFont(CReport_Builder_Object_Font::fromXml($xmlElement));
            }
        }
        $report->addChildrenFromXml($xml);

        return $report;
    }

    /**
     * @param SimpleXMLElement $xml
     *
     * @return $this
     */
    public function addChildrenFromXml(SimpleXMLElement $xml) {
        foreach ($xml as $tag => $xmlElement) {
            if (!CReport_Builder_ElementFactory::isIgnore($tag)) {
                $this->addChildren(CReport_Builder_ElementFactory::createElementFromXml($tag, $xmlElement));
            }
        }

        return $this;
    }

    /**
     * @param CReport_Builder_ElementAbstract $element
     *
     * @return $this
     */
    public function addChildren(CReport_Builder_ElementAbstract $element) {
        $this->children[] = $element;

        return $this;
    }

    /**
     * Set default font for the report. This font will be used for all elements that doesn't have font defined.
     *
     * @param CReport_Builder_Object_Font $font
     *
     * @return $this
     */
    public function setDefaultFont(CReport_Builder_Object_Font $font) {
        $this->defaultFont = $font;

        return $this;
    }

    /**
     * Get default font for the report. This font will be used for all elements that doesn't have font defined.
     *
     * @return CReport_Builder_Object_Font
     */
    public function getDefaultFont() {
        return $this->defaultFont;
    }

    /**
     * @param float $width
     *
     * @return $this
     */
    public function setPageWidth(float $width) {
        $this->pageWidth = $width;

        return $this;
    }

    /**
     * @param float $height
     *
     * @return $this
     */
    public function setPageHeight(float $height) {
        $this->pageHeight = $height;

        return $this;
    }

    /**
     * @param float $topMargin
     *
     * @return $this
     */
    public function setTopMargin(float $topMargin) {
        $this->topMargin = $topMargin;

        return $this;
    }

    /**
     * @param float $bottomMargin
     *
     * @return $this
     */
    public function setBottomMargin(float $bottomMargin) {
        $this->bottomMargin = $bottomMargin;

        return $this;
    }

    /**
     * @param float $rightMargin
     *
     * @return $this
     */
    public function setRightMargin(float $rightMargin) {
        $this->rightMargin = $rightMargin;

        return $this;
    }

    /**
     * @param float $leftMargin
     *
     * @return $this
     */
    public function setLeftMargin($leftMargin) {
        $this->leftMargin = $leftMargin;

        return $this;
    }

    /**
     * @param float $columnWidth
     *
     * @return $this
     */
    public function setColumnWidth($columnWidth) {
        $this->columnWidth = $columnWidth;

        return $this;
    }

    /**
     * @param string $orientation
     *
     * @return $this
     */
    public function setOrientation($orientation) {
        $this->orientation = $orientation;

        return $this;
    }

    /**
     * @return float
     */
    public function getPageWidth() {
        return $this->pageWidth;
    }

    /**
     * @return float
     */
    public function getPageHeight() {
        return $this->pageHeight;
    }

    /**
     * @return string
     */
    public function getOrientation() {
        return $this->orientation;
    }

    /**
     * @return float
     */
    public function getLeftMargin() {
        return $this->leftMargin;
    }

    /**
     * @return float
     */
    public function getTopMargin() {
        return $this->topMargin;
    }

    /**
     * @return float
     */
    public function getRightMargin() {
        return $this->rightMargin;
    }

    /**
     * @return float
     */
    public function getBottomMargin() {
        return $this->bottomMargin;
    }

    /**
     * @return float
     */
    public function getContainerWidth() {
        return $this->pageWidth - ($this->leftMargin + $this->rightMargin);
    }

    /**
     * @return float
     */
    public function getContainerHeight() {
        return $this->pageHeight - ($this->topMargin + $this->bottomMargin);
    }

    /**
     * @return CReport_Builder_Element_Group
     */
    public function addGroup() {
        $group = new CReport_Builder_Element_Group();
        $this->children[] = $group;

        return $group;
    }

    /**
     * @return CReport_Builder_Element_Variable
     */
    public function addVariable() {
        $variable = new CReport_Builder_Element_Variable();
        $this->children[] = $variable;

        return $variable;
    }

    /**
     * @return string
     */
    protected function getDefaultFontTag() {
        return '<defaultFont '
        . ' name="' . $this->getFontName() . '"'
        . ' size="' . $this->getFontSize() . '"'
        . ' isBold="' . CReport_Builder_PhpToJrXmlEnum::getBoolEnum($this->fontIsBold()) . '"'
        . ' isItalic="' . CReport_Builder_PhpToJrXmlEnum::getBoolEnum($this->fontIsItalic()) . '"'
        . ' isUnderline="' . CReport_Builder_PhpToJrXmlEnum::getBoolEnum($this->fontIsUnderline()) . '"'
        . ' isStrikeThrough="' . CReport_Builder_PhpToJrXmlEnum::getBoolEnum($this->fontIsStrikeThrough()) . '"'
        . '/>';
    }

    /**
     * @return string
     */
    public function toJrXml() {
        $openTag = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL
            . '<jasperReport xmlns="http://jasperreports.sourceforge.net/jasperreports"' . PHP_EOL
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . PHP_EOL
            . 'xsi:schemaLocation="http://jasperreports.sourceforge.net/jasperreports http://jasperreports.sourceforge.net/xsd/jasperreport.xsd"' . PHP_EOL;
        $openTag .= 'name="' . $this->name . '"' . PHP_EOL;
        $openTag .= 'pageWidth="' . $this->pageWidth . '"' . PHP_EOL;
        $openTag .= 'pageHeight="' . $this->pageHeight . '"' . PHP_EOL;
        $openTag .= 'columnWidth="' . $this->columnWidth . '"' . PHP_EOL;
        $openTag .= 'leftMargin="' . $this->leftMargin . '"' . PHP_EOL;
        $openTag .= 'rightMargin="' . $this->rightMargin . '"' . PHP_EOL;
        $openTag .= 'topMargin="' . $this->topMargin . '"' . PHP_EOL;
        $openTag .= 'bottomMargin="' . $this->bottomMargin . '"' . PHP_EOL;
        $openTag .= 'orientation="' . ($this->orientation == CReport_Paper::ORIENTATION_LANDSCAPE ? 'Landscape' : 'Portrait') . '"' . PHP_EOL;
        $openTag .= '>';
        $body = '';
        $body .= $this->getDefaultFontTag();
        foreach ($this->children as $child) {
            $body .= $child->toJrXml();
        }
        $closeTag = '</jasperReport>';

        return $openTag . PHP_EOL . $body . PHP_EOL . $closeTag;
    }

    /**
     * @return void
     */
    public function toJson() {
    }

    /**
     * Generate all root bands in order. Group elements are skipped because their
     * header/footer bands are generated by the detail band on group break.
     *
     * @param CReport_Generator                    $generator
     * @param CReport_Generator_ProcessorAbstract $processor
     *
     * @return void
     */
    public function generate(CReport_Generator $generator, CReport_Generator_ProcessorAbstract $processor) {
        foreach ($this->children as $child) {
            if ($child instanceof CReport_Builder_Element_Group) {
                continue;
            }
            $generator->setCurrentBand($child);
            $child->generate($generator, $processor);
        }
    }

    /**
     * @return CCollection|CReport_Builder_Element_Group[]
     */
    public function getGroupElements() {
        return c::collect($this->children)->filter(function ($value) {
            return $value instanceof CReport_Builder_Element_Group;
        });
    }

    /**
     * @return CCollection|CReport_Builder_Element_Variable[]
     */
    public function getVariableElements() {
        return c::collect($this->children)->filter(function ($value) {
            return $value instanceof CReport_Builder_Element_Variable;
        });
    }

    /**
     * @return CCollection|CReport_Builder_Element_ColumnHeader[]
     */
    public function getColumnHeaderElements() {
        return c::collect($this->children)->filter(function ($value) {
            return $value instanceof CReport_Builder_Element_ColumnHeader;
        });
    }

    /**
     * @return null|CReport_Builder_Element_ColumnHeader
     */
    public function getColumnHeaderElement() {
        return $this->getColumnHeaderElements()->first();
    }

    /**
     * @return CCollection|CReport_Builder_Element_PageHeader[]
     */
    public function getPageHeaderElements() {
        return c::collect($this->children)->filter(function ($value) {
            return $value instanceof CReport_Builder_Element_PageHeader;
        });
    }

    /**
     * @return null|CReport_Builder_Element_PageHeader
     */
    public function getPageHeaderElement() {
        return $this->getPageHeaderElements()->first();
    }

    /**
     * @return CCollection|CReport_Builder_Element_PageFooter[]
     */
    public function getPageFooterElements() {
        return c::collect($this->children)->filter(function ($value) {
            return $value instanceof CReport_Builder_Element_PageFooter;
        });
    }

    /**
     * @return null|CReport_Builder_Element_PageFooter
     */
    public function getPageFooterElement() {
        return $this->getPageFooterElements()->first();
    }

    /**
     * @return CCollection|CReport_Builder_Element_ColumnFooter[]
     */
    public function getColumnFooterElements() {
        return c::collect($this->children)->filter(function ($value) {
            return $value instanceof CReport_Builder_Element_ColumnFooter;
        });
    }

    /**
     * @return null|CReport_Builder_Element_ColumnFooter
     */
    public function getColumnFooterElement() {
        return $this->getColumnFooterElements()->first();
    }

    /**
     * @return CCollection|CReport_Builder_Element_Style[]
     */
    public function getStyleElements() {
        return c::collect($this->children)->filter(function ($value) {
            return $value instanceof CReport_Builder_Element_Style;
        });
    }

    /**
     * @return CCollection|CReport_Builder_Element_Font[]
     */
    public function getFontElements() {
        return c::collect($this->children)->filter(function ($value) {
            return $value instanceof CReport_Builder_Element_Font;
        });
    }
}
