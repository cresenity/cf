<?php

class CDocument_Pdf_Element_Rectangle extends CDocument_Pdf_ElementAbstract {
    // CONSTANTS:

    /**
     * This is the value that will be used as <VAR>undefined </VAR>.
     */
    const UNDEFINED = -1;

    /**
     * This represents one side of the border of the <CODE>Rectangle</CODE>.
     */
    const TOP = 1;

    /**
     * This represents one side of the border of the <CODE>Rectangle</CODE>.
     */
    const BOTTOM = 2;

    /**
     * This represents one side of the border of the <CODE>Rectangle</CODE>.
     */
    const LEFT = 4;

    /**
     * This represents one side of the border of the <CODE>Rectangle</CODE>.
     */
    const RIGHT = 8;

    /**
     * This represents a rectangle without borders.
     */
    const NO_BORDER = 0;

    /**
     * This represents a type of border.
     */
    const BOX = self::TOP + self::BOTTOM + self::LEFT + self::RIGHT;

    // MEMBER VARIABLES:

    /**
     * The lower left x-coordinate.
     *
     * @var float
     */
    protected $llx;

    /**
     * The lower left y-coordinate.
     *
     * @var float
     */
    protected $lly;

    /**
     * The upper right x-coordinate.
     *
     * @var float
     */
    protected $urx;

    /**
     * The upper right y-coordinate.
     *
     * @var float
     */
    protected $ury;

    /**
     * The offset relative to a certain top.
     *
     * @var float
     */
    protected $offsetToTop;

    /**
     * The rotation of the Rectangle.
     *
     * @var float
     */
    protected $rotation = 0;

    /**
     * This is the color of the background of this rectangle.
     *
     * @var CColor_FormatAbstract
     */
    protected $backgroundColor = null;

    /**
     * This represents the status of the 4 sides of the rectangle.
     *
     * @var int
     */
    protected $border = self::UNDEFINED;

    /**
     * Whether variable width/color borders are used.
     *
     * @var bool
     */
    protected $useVariableBorders = false;

    /**
     * This is the width of the border around this rectangle.
     *
     * @var float
     */
    protected $borderWidth = self::UNDEFINED;

    /**
     * The width of the left border of this rectangle.
     *
     * @var float
     */
    protected $borderWidthLeft = self::UNDEFINED;

    /**
     * The width of the right border of this rectangle.
     *
     * @var float
     */
    protected $borderWidthRight = self::UNDEFINED;

    /**
     * The width of the top border of this rectangle.
     *
     * @var float
     */
    protected $borderWidthTop = self::UNDEFINED;

    /**
     * The width of the bottom border of this rectangle.
     *
     * @var float
     */
    protected $borderWidthBottom = self::UNDEFINED;

    /**
     * The color of the border of this rectangle.
     *
     * @var null|CColor_FormatAbstract
     */
    protected $borderColor = null;

    /**
     * The color of the left border of this rectangle.
     *
     * @var null|CColor_FormatAbstract
     */
    protected $borderColorLeft = null;

    /**
     * The color of the right border of this rectangle.
     *
     * @var null|CColor_FormatAbstract
     */
    protected $borderColorRight = null;

    /**
     * The color of the top border of this rectangle.
     *
     * @var null|CColor_FormatAbstract
     */
    protected $borderColorTop = null;

    /**
     * The color of the bottom border of this rectangle.
     *
     * @var null|CColor_FormatAbstract
     */
    protected $borderColorBottom = null;

    // CONSTRUCTORS:

    /**
     * Constructs a <CODE>Rectangle</CODE> -object.
     *
     * @param float|CDocument_Pdf_Element_Rectangle llx lower left x
     * @param float $lly lower left y
     * @param float $urx upper right x
     * @param float $ury upper right y
     * @param mixed $llx
     */
    public function __construct($llx, float $lly = 0, float $urx = 0, float $ury = 0, ?int $rotation = null) {
        if ($llx instanceof CDocument_Pdf_Element_Rectangle) {
            $lly = $llx->lly;
            $urx = $llx->urx;
            $ury = $llx->ury;
            $this->cloneNonPositionParameters($llx);
            $llx = $llx->llx;
        }
        $this->llx = $llx;
        $this->lly = $lly;
        $this->urx = $urx;
        $this->ury = $ury;
        if ($rotation) {
            $this->setRotation($rotation);
        }
    }

    /**
     * Processes the element by adding it (or the different parts) to an
     * <CODE>ElementListener</CODE>.
     *
     * @param CEvent_Dispatcher $event listener an
     *
     * @return bool
     */
    public function process(?CEvent_Dispatcher $event = null) {
        // try {
        //     return listener.add(this);
        // } catch (DocumentException de) {
        //     return false;
        // }
    }

    // IMPLEMENTATION OF THE ELEMENT INTERFACE:e

    /**
     * Gets the type of the text element.
     *
     * @return int
     */
    public function type() {
        return CDocument_Pdf_ElementConstant::RECTANGLE;
    }

    public function getChunks() {
        return [];
    }

    public function isContent() {
        return true;
    }

    public function isNestable() {
        return false;
    }

    /**
     * Returns the lower left x-coordinate.
     *
     * @return float
     */
    public function getLeft(float $margin = 0) {
        return $this->llx + $margin;
    }

    // METHODS TO GET/SET THE DIMENSIONS:

    /**
     * Sets the lower left x-coordinate.
     *
     * @param float $llx the new value
     */
    public function setLeft(float $llx) {
        $this->llx = $llx;
    }

    /**
     * Returns the upper right x-coordinate.
     *
     * @return float
     */
    public function getRight(float $margin = 0) {
        return $this->urx + $margin;
    }

    /**
     * Sets the upper right x-coordinate.
     *
     * @param float $urx the new value
     */
    public function setRight(float $urx) {
        $this->urx = $urx;
    }

    /**
     * Returns the width of the rectangle.
     *
     * @return float
     */
    public function getWidth() {
        return $this->urx - $this->llx;
    }

    /**
     * Returns the upper right y-coordinate.
     *
     * @return float
     */
    public function getTop(float $margin = 0) {
        return $this->ury + $margin;
    }

    /**
     * Sets the upper right y-coordinate.
     *
     * @param float $ury the new value
     */
    public function setTop(float $ury) {
        $this->ury = $ury;
    }

    /**
     * Gets offset relative to top.
     *
     * @return float
     */
    public function getRelativeTop() {
        return $this->offsetToTop;
    }

    /**
     * Sets offset relative to top.
     *
     * @param float $offsetToTop
     */
    public function setRelativeTop(float $offsetToTop) {
        $this->offsetToTop = $offsetToTop;
    }

    /**
     * Returns the lower left y-coordinate.
     *
     * @return float
     */
    public function getBottom(float $margin = 0) {
        return $this->lly + $margin;
    }

    /**
     * Sets the lower left y-coordinate.
     *
     * @param float $lly the new value
     */
    public function setBottom(float $lly) {
        $this->lly = $lly;
    }

    /**
     * Returns the height of the rectangle.
     *
     * @return float
     */
    public function getHeight() {
        return $this->ury - $this->lly;
    }

    /**
     * Normalizes the rectangle. Switches lower left with upper right if necessary.
     */
    public function normalize() {
        if ($this->llx > $this->urx) {
            $a = $this->llx;
            $this->llx = $this->urx;
            $this->urx = $a;
        }
        if ($this->lly > $this->ury) {
            $a = $this->lly;
            $this->lly = $this->ury;
            $this->ury = $a;
        }
    }

    /**
     * Gets the rotation of the rectangle.
     *
     * @return int
     */
    public function getRotation() {
        return $this->rotation;
    }

    /**
     * Sets the rotation of the rectangle. Valid values are 0, 90, 180, and 270.
     *
     * @param int $rotation the new rotation value
     */
    public function setRotation(int $rotation) {
        $mod = $rotation % 360;
        if (($mod == 90) || ($mod == 180) || ($mod == 270)) {
            $this->rotation = $mod;
        } else {
            $this->rotation = 0;
        }
    }

    /**
     * Rotates the rectangle. Swaps the values of llx and lly and of urx and ury.
     *
     * @return CDocument_Pdf_Element_Rectangle
     */
    public function rotate() {
        $rect = new self($this->lly, $this->llx, $this->ury, $this->urx);
        $rect->rotation = $this->rotation + 90;
        $rect->rotation %= 360;

        return $rect;
    }

    // METHODS TO GET/SET THE BACKGROUND COLOR:

    /**
     * Gets the backgroundcolor.
     *
     * @return CColor_FormatAbstract
     */
    public function getBackgroundColor() {
        return $this->backgroundColor;
    }

    /**
     * Sets the backgroundcolor of the rectangle.
     *
     * @param CColor_FormatAbstract $backgroundColor a <CODE>Color</CODE>
     */
    public function setBackgroundColor(CColor_FormatAbstract $backgroundColor) {
        $this->backgroundColor = $backgroundColor;
    }

    /**
     * Gets the grayscale.
     *
     * @return float
     */
    public function getGrayFill() {
        if ($this->backgroundColor instanceof CDocument_Pdf_Object_GrayColor) {
            return $this->backgroundColor->getGray();
        }

        return 0;
    }

    /**
     * Sets the the background color to a grayscale value.
     *
     * @param float $value the new grayscale value
     */
    public function setGrayFill(float $value) {
        $this->backgroundColor = new CDocument_Pdf_Object_GrayColor($value);
    }

    // METHODS TO GET/SET THE BORDER:

    /**
     * Returns the exact type of the border.
     *
     * @return int
     */
    public function getBorder() {
        return $this->border;
    }

    /**
     * Enables/Disables the border on the specified sides. The border is specified as an integer bitwise combination of
     * the constants:.
     *
     * @param int $border the new value
     */
    public function setBorder(int $border) {
        $this->border = $border;
    }

    /**
     * Indicates whether some type of border is set.
     *
     * @return bool
     */
    public function hasBorders() {
        switch ($this->border) {
            case self::UNDEFINED:
            case self::NO_BORDER:
                return false;
            default:
                return $this->borderWidth > 0 || $this->borderWidthLeft > 0 || $this->borderWidthRight > 0
                        || $this->borderWidthTop > 0 || $this->borderWidthBottom > 0;
        }
    }

    /**
     * Indicates whether the specified type of border is set.
     *
     * @param int $type the type of border
     *
     * @return bool
     */
    public function hasBorder(int $type) {
        if ($this->border == self::UNDEFINED) {
            return false;
        }

        return ($this->border & $type) == $type;
    }

    /**
     * Indicates whether variable width borders are being used. Returns true if
     * <CODE>setBorderWidthLeft, setBorderWidthRight,
     * setBorderWidthTop, or setBorderWidthBottom</CODE> has been called.
     * true if variable width borders are in use.
     *
     * @return bool
     */
    public function isUseVariableBorders() {
        return $this->useVariableBorders;
    }

    /**
     * Sets a parameter indicating if the rectangle has variable borders.
     *
     * @param bool $useVariableBorders indication if the rectangle has variable borders
     */
    public function setUseVariableBorders(bool $useVariableBorders) {
        $this->useVariableBorders = $useVariableBorders;
    }

    /**
     * Enables the border on the specified side.
     *
     * @param int $side the side to enable. One of <CODE>LEFT, RIGHT, TOP, BOTTOM</CODE>
     */
    public function enableBorderSide(int $side) {
        if ($this->border == self::UNDEFINED) {
            $this->border = 0;
        }
        $this->border |= $side;
    }

    /**
     * Disables the border on the specified side.
     *
     * @param int $side the side to disable. One of <CODE>LEFT, RIGHT, TOP, BOTTOM</CODE>
     */
    public function disableBorderSide(int $side) {
        if ($this->border == self::UNDEFINED) {
            $this->border = 0;
        }
        $this->border &= ~$side;
    }

    // METHODS TO GET/SET THE BORDER WIDTH:

    /**
     * Gets the borderwidth.
     *
     * @return float
     */
    public function getBorderWidth() {
        return $this->borderWidth;
    }

    /**
     * Sets the borderwidth of the table.
     *
     * @param float $borderWidth the new value
     */
    public function setBorderWidth(float $borderWidth) {
        $this->borderWidth = $borderWidth;
    }

    /**
     * Helper function returning the border width of a specific side.
     * return the variableWidthValue if not undefined, otherwise the borderWidth.
     *
     * @param float $variableWidthValue a variable width (could be undefined)
     * @param int   $side               the border you want to check
     *
     * @return float
     */
    private function getVariableBorderWidth(float $variableWidthValue, int $side) {
        if (($this->border & $side) != 0) {
            return $variableWidthValue != self::UNDEFINED ? $variableWidthValue : $this->borderWidth;
        }

        return 0;
    }

    /**
     * Helper function updating the border flag for a side based on the specified width. A width of 0 will disable the
     * border on that side. Any other width enables it.
     *
     * @param float $width width of border
     * @param int   $side  border side constant
     */
    private function updateBorderBasedOnWidth(float $width, int $side) {
        $this->useVariableBorders = true;
        if ($width > 0) {
            $this->enableBorderSide($side);
        } else {
            $this->disableBorderSide($side);
        }
    }

    /**
     * Gets the width of the left border.
     *
     * @return float
     */
    public function getBorderWidthLeft() {
        return $this->getVariableBorderWidth($this->borderWidthLeft, self::LEFT);
    }

    /**
     * Sets the width of the left border.
     *
     * @param float $borderWidthLeft a width
     */
    public function setBorderWidthLeft(float $borderWidthLeft) {
        $this->borderWidthLeft = $borderWidthLeft;
        $this->updateBorderBasedOnWidth($borderWidthLeft, self::LEFT);
    }

    /**
     * Gets the width of the right border.
     *
     * @return float
     */
    public function getBorderWidthRight() {
        return $this->getVariableBorderWidth($this->borderWidthRight, self::RIGHT);
    }

    /**
     * Sets the width of the right border.
     *
     * @param float $borderWidthRight a width
     */
    public function setBorderWidthRight(float $borderWidthRight) {
        $this->borderWidthRight = $borderWidthRight;
        $this->updateBorderBasedOnWidth($borderWidthRight, self::RIGHT);
    }

    /**
     * Gets the width of the top border.
     *
     * @return float
     */
    public function getBorderWidthTop() {
        return $this->getVariableBorderWidth($this->borderWidthTop, self::TOP);
    }

    /**
     * Sets the width of the top border.
     *
     * @param float $borderWidthTop a width
     */
    public function setBorderWidthTop(float $borderWidthTop) {
        $this->borderWidthTop = $borderWidthTop;
        $this->updateBorderBasedOnWidth($borderWidthTop, self::TOP);
    }

    /**
     * Gets the width of the bottom border.
     *
     * @return float
     */
    public function getBorderWidthBottom() {
        return $this->getVariableBorderWidth($this->borderWidthBottom, self::BOTTOM);
    }

    /**
     * Sets the width of the bottom border.
     *
     * @param float $borderWidthBottom a width
     */
    public function setBorderWidthBottom(float $borderWidthBottom) {
        $this->borderWidthBottom = $borderWidthBottom;
        $this->updateBorderBasedOnWidth($borderWidthBottom, self::BOTTOM);
    }

    // METHODS TO GET/SET THE BORDER COLOR:

    /**
     * Gets the color of the border.
     *
     * @return CColor_FormatAbstract
     */
    public function getBorderColor() {
        return $this->borderColor;
    }

    /**
     * Sets the color of the border.
     *
     * @param CColor_FormatAbstract $borderColor a <CODE>Color</CODE>
     */
    public function setBorderColor(CColor_FormatAbstract $borderColor) {
        $this->borderColor = $borderColor;
    }

    /**
     * Gets the color of the left border.
     *
     * @return CColor_FormatAbstract
     */
    public function getBorderColorLeft() {
        if ($this->borderColorLeft == null) {
            return $this->borderColor;
        }

        return $this->borderColorLeft;
    }

    /**
     * Sets the color of the left border.
     *
     * @param CColor_FormatAbstract $borderColorLeft a <CODE>Color</CODE>
     */
    public function setBorderColorLeft(CColor_FormatAbstract $borderColorLeft) {
        $this->borderColorLeft = $borderColorLeft;
    }

    /**
     * Gets the color of the right border.
     *
     * @return CColor_FormatAbstract
     */
    public function getBorderColorRight() {
        if ($this->borderColorRight == null) {
            return $this->borderColor;
        }

        return $this->borderColorRight;
    }

    /**
     * Sets the color of the right border.
     *
     * @param CColor_FormatAbstract $borderColorRight a <CODE>Color</CODE>
     */
    public function setBorderColorRight(CColor_FormatAbstract $borderColorRight) {
        $this->borderColorRight = $borderColorRight;
    }

    /**
     * Gets the color of the top border.
     *
     * @return CColor_FormatAbstract
     */
    public function getBorderColorTop() {
        if ($this->borderColorTop == null) {
            return $this->borderColor;
        }

        return $this->borderColorTop;
    }

    /**
     * Sets the color of the top border.
     *
     * @param CColor_FormatAbstract $borderColorTop a <CODE>Color</CODE>
     */
    public function setBorderColorTop(CColor_FormatAbstract $borderColorTop) {
        $this->borderColorTop = $borderColorTop;
    }

    /**
     * Gets the color of the bottom border.
     *
     * @return CColor_FormatAbstract
     */
    public function getBorderColorBottom() {
        if ($this->borderColorBottom == null) {
            return $this->borderColor;
        }

        return $this->borderColorBottom;
    }

    /**
     * Sets the color of the bottom border.
     *
     * @param CColor_FormatAbstract $borderColorBottom a <CODE>Color</CODE>
     */
    public function setBorderColorBottom(CColor_FormatAbstract $borderColorBottom) {
        $this->borderColorBottom = $borderColorBottom;
    }

    // SPECIAL METHODS:

    /**
     * Gets a Rectangle that is altered to fit on the page.
     *
     * @param float $top    the top position
     * @param float $bottom the bottom position
     *
     * @return CDocument_Pdf_Element_Rectangle
     */
    public function rectangle(float $top, float $bottom) {
        $tmp = new CDocument_Pdf_Element_Rectangle($this);
        if ($this->getTop() > $top) {
            $tmp->setTop($top);
            $tmp->disableBorderSide(self::TOP);
        }
        if ($this->getBottom() < $bottom) {
            $tmp->setBottom($bottom);
            $tmp->disableBorderSide(self::BOTTOM);
        }

        return $tmp;
    }

    /**
     * Copies each of the parameters, except the position, from a
     * <CODE>Rectangle</CODE> object.
     *
     * @param CDocument_Pdf_Element_Rectangle $rect <CODE>Rectangle</CODE> to copy from
     */
    public function cloneNonPositionParameters(CDocument_Pdf_Element_Rectangle $rect) {
        $this->rotation = $rect->rotation;
        $this->backgroundColor = $rect->backgroundColor;
        $this->border = $rect->border;
        $this->useVariableBorders = $rect->useVariableBorders;
        $this->borderWidth = $rect->borderWidth;
        $this->borderWidthLeft = $rect->borderWidthLeft;
        $this->borderWidthRight = $rect->borderWidthRight;
        $this->borderWidthTop = $rect->borderWidthTop;
        $this->borderWidthBottom = $rect->borderWidthBottom;
        $this->borderColor = $rect->borderColor;
        $this->borderColorLeft = $rect->borderColorLeft;
        $this->borderColorRight = $rect->borderColorRight;
        $this->borderColorTop = $rect->borderColorTop;
        $this->borderColorBottom = $rect->borderColorBottom;
    }

    /**
     * Copies each of the parameters, except the position, from a
     * <CODE>Rectangle</CODE> object if the value is set there.
     *
     * @param CDocument_Pdf_Element_Rectangle $rect
     */
    public function softCloneNonPositionParameters(CDocument_Pdf_Element_Rectangle $rect) {
        if ($rect->rotation != 0) {
            $this->rotation = $rect->rotation;
        }
        if ($rect->backgroundColor != null) {
            $this->backgroundColor = $rect->backgroundColor;
        }
        if ($rect->border != self::UNDEFINED) {
            $this->border = $rect->border;
        }
        if ($this->useVariableBorders) {
            $this->useVariableBorders = $rect->useVariableBorders;
        }
        if ($rect->borderWidth != self::UNDEFINED) {
            $this->borderWidth = $rect->borderWidth;
        }
        if ($rect->borderWidthLeft != self::UNDEFINED) {
            $this->borderWidthLeft = $rect->borderWidthLeft;
        }
        if ($rect->borderWidthRight != self::UNDEFINED) {
            $this->borderWidthRight = $rect->borderWidthRight;
        }
        if ($rect->borderWidthTop != self::UNDEFINED) {
            $this->borderWidthTop = $rect->borderWidthTop;
        }
        if ($rect->borderWidthBottom != self::UNDEFINED) {
            $this->borderWidthBottom = $rect->borderWidthBottom;
        }
        if ($rect->borderColor != null) {
            $this->borderColor = $rect->borderColor;
        }
        if ($rect->borderColorLeft != null) {
            $this->borderColorLeft = $rect->borderColorLeft;
        }
        if ($rect->borderColorRight != null) {
            $this->borderColorRight = $rect->borderColorRight;
        }
        if ($rect->borderColorTop != null) {
            $this->borderColorTop = $rect->borderColorTop;
        }
        if ($rect->borderColorBottom != null) {
            $this->borderColorBottom = $rect->borderColorBottom;
        }
    }

    /**
     * Return String representation of the rectangle.
     *
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function __toString() {
        $string = 'Rectangle: ';
        $string .= $this->getWidth();
        $string .= 'x';
        $string .= $this->getHeight();
        $string .= ' (rot: ';
        $string .= $this->rotation;
        $string .= ' degrees)';

        return $string;
    }
}
