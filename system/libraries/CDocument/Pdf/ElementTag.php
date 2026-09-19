<?php
class CDocument_Pdf_ElementTag {
    /**
     * The root tag.
     */
    const ITEXT = 'itext';

    /**
     * Attribute of the root and annotation tag (also a special tag within a chapter or section).
     */
    const TITLE = 'title';

    /**
     * Attribute of the root tag.
     */
    const SUBJECT = 'subject';

    /**
     * Attribute of the root tag.
     */
    const KEYWORDS = 'keywords';

    /**
     * Attribute of the root tag.
     */
    const AUTHOR = 'author';

    /**
     * Attribute of the root tag.
     */
    const CREATIONDATE = 'creationdate';

    /**
     * Attribute of the root tag.
     */
    const PRODUCER = 'producer';

    // Chapters and Sections

    /**
     * The chapter tag.
     */
    const CHAPTER = 'chapter';

    /**
     * The section tag.
     */
    const SECTION = 'section';

    /**
     * Attribute of section/chapter tag.
     */
    const NUMBERDEPTH = 'numberdepth';

    /**
     * Attribute of section/chapter tag.
     */
    const DEPTH = 'depth';

    /**
     * Attribute of section/chapter tag.
     */
    const NUMBER = 'number';

    /**
     * Attribute of section/chapter tag.
     */
    const INDENT = 'indent';

    /**
     * Attribute of chapter/section/paragraph/table/cell tag.
     */
    const LEFT = 'left';

    /**
     * Attribute of chapter/section/paragraph/table/cell tag.
     */
    const RIGHT = 'right';

    // Phrases, Anchors, Lists and Paragraphs

    /**
     * The phrase tag.
     */
    const PHRASE = 'phrase';

    /**
     * The anchor tag.
     */
    const ANCHOR = 'anchor';

    /**
     * The list tag.
     */
    const LIST = 'list';

    /**
     * The listitem tag.
     */
    const LISTITEM = 'listitem';

    /**
     * The paragraph tag.
     */
    const PARAGRAPH = 'paragraph';

    /**
     * Attribute of phrase/paragraph/cell tag.
     */
    const LEADING = 'leading';

    /**
     * Attribute of paragraph/image/table tag.
     */
    const ALIGN = 'align';

    /**
     * Attribute of paragraph.
     */
    const KEEPTOGETHER = 'keeptogether';

    /**
     * Attribute of anchor tag.
     */
    const NAME = 'name';

    /**
     * Attribute of anchor tag.
     */
    const REFERENCE = 'reference';

    /**
     * Attribute of list tag.
     */
    const LISTSYMBOL = 'listsymbol';

    /**
     * Attribute of list tag.
     */
    const NUMBERED = 'numbered';

    /**
     * Attribute of the list tag.
     */
    const LETTERED = 'lettered';

    /**
     * Attribute of list tag.
     */
    const FIRST = 'first';

    /**
     * Attribute of list tag.
     */
    const SYMBOLINDENT = 'symbolindent';

    /**
     * Attribute of list tag.
     */
    const INDENTATIONLEFT = 'indentationleft';

    /**
     * Attribute of list tag.
     */
    const INDENTATIONRIGHT = 'indentationright';

    // Chunks

    /**
     * The chunk tag.
     */
    const IGNORE = 'ignore';

    /**
     * The chunk tag.
     */
    const ENTITY = 'entity';

    /**
     * The chunk tag.
     */
    const ID = 'id';

    /**
     * The chunk tag.
     */
    const CHUNK = 'chunk';

    /**
     * Attribute of the chunk tag.
     */
    const ENCODING = 'encoding';

    /**
     * Attribute of the chunk tag.
     */
    const EMBEDDED = 'embedded';

    /**
     * Attribute of the chunk/table/cell tag.
     */
    const COLOR = 'color';

    /**
     * Attribute of the chunk/table/cell tag.
     */
    const RED = 'red';

    /**
     * Attribute of the chunk/table/cell tag.
     */
    const GREEN = 'green';

    /**
     * Attribute of the chunk/table/cell tag.
     */
    const BLUE = 'blue';

    /**
     * Attribute of the chunk tag.
     */
    const SUBSUPSCRIPT = 'subsupscript';

    /**
     * Attribute of the chunk tag.
     */
    const LOCALGOTO = 'localgoto';

    /**
     * Attribute of the chunk tag.
     */
    const REMOTEGOTO = 'remotegoto';

    /**
     * Attribute of the chunk tag.
     */
    const LOCALDESTINATION = 'localdestination';

    /**
     * Attribute of the chunk tag.
     */
    const GENERICTAG = 'generictag';

    // tables/cells

    /**
     * The table tag.
     */
    const TABLE = 'table';

    /**
     * The cell tag.
     */
    const ROW = 'row';

    /**
     * The cell tag.
     */
    const CELL = 'cell';

    /**
     * Attribute of the table tag.
     */
    const COLUMNS = 'columns';

    /**
     * Attribute of the table tag.
     */
    const LASTHEADERROW = 'lastHeaderRow';

    /**
     * Attribute of the table tag.
     */
    const CELLPADDING = 'cellpadding';

    /**
     * Attribute of the table tag.
     */
    const CELLSPACING = 'cellspacing';

    /**
     * Attribute of the table tag.
     */
    const OFFSET = 'offset';

    /**
     * Attribute of the table tag.
     */
    const WIDTHS = 'widths';

    /**
     * Attribute of the table tag.
     */
    const TABLEFITSPAGE = 'tablefitspage';

    /**
     * Attribute of the table tag.
     */
    const CELLSFITPAGE = 'cellsfitpage';

    /**
     * Attribute of the table tag.
     */
    const CONVERT2PDFP = 'convert2pdfp';

    /**
     * Attribute of the cell tag.
     */
    const HORIZONTALALIGN = 'horizontalalign';

    /**
     * Attribute of the cell tag.
     */
    const VERTICALALIGN = 'verticalalign';

    /**
     * Attribute of the cell tag.
     */
    const COLSPAN = 'colspan';

    /**
     * Attribute of the cell tag.
     */
    const ROWSPAN = 'rowspan';

    /**
     * Attribute of the cell tag.
     */
    const HEADER = 'header';

    /**
     * Attribute of the cell tag.
     */
    const NOWRAP = 'nowrap';

    /**
     * Attribute of the table/cell tag.
     */
    const BORDERWIDTH = 'borderwidth';

    /**
     * Attribute of the table/cell tag.
     */
    const TOP = 'top';

    /**
     * Attribute of the table/cell tag.
     */
    const BOTTOM = 'bottom';

    /**
     * Attribute of the table/cell tag.
     */
    const WIDTH = 'width';

    /**
     * Attribute of the table/cell tag.
     */
    const BORDERCOLOR = 'bordercolor';

    /**
     * Attribute of the table/cell tag.
     */
    const BACKGROUNDCOLOR = 'backgroundcolor';

    /**
     * Attribute of the table/cell tag.
     */
    const BGRED = 'bgred';

    /**
     * Attribute of the table/cell tag.
     */
    const BGGREEN = 'bggreen';

    /**
     * Attribute of the table/cell tag.
     */
    const BGBLUE = 'bgblue';

    /**
     * Attribute of the table/cell tag.
     */
    const GRAYFILL = 'grayfill';

    // Misc

    /**
     * The image tag.
     */
    const IMAGE = 'image';

    /**
     * Attribute of the image and annotation tag.
     */
    const URL = 'url';

    /**
     * Attribute of the image tag.
     */
    const UNDERLYING = 'underlying';

    /**
     * Attribute of the image tag.
     */
    const TEXTWRAP = 'textwrap';

    /**
     * Attribute of the image tag.
     */
    const ALT = 'alt';

    /**
     * Attribute of the image tag.
     */
    const ABSOLUTEX = 'absolutex';

    /**
     * Attribute of the image tag.
     */
    const ABSOLUTEY = 'absolutey';

    /**
     * Attribute of the image tag.
     */
    const PLAINWIDTH = 'plainwidth';

    /**
     * Attribute of the image tag.
     */
    const PLAINHEIGHT = 'plainheight';

    /**
     * Attribute of the image tag.
     */
    const SCALEDWIDTH = 'scaledwidth';

    /**
     * Attribute of the image tag.
     */
    const SCALEDHEIGHT = 'scaledheight';

    /**
     * Attribute of the image tag.
     */
    const ROTATION = 'rotation';

    /**
     * The newpage tag.
     */
    const NEWPAGE = 'newpage';

    /**
     * The newpage tag.
     */
    const NEWLINE = 'newline';

    /**
     * The annotation tag.
     */
    const ANNOTATION = 'annotation';

    /**
     * Attribute of the annotation tag.
     */
    const FILE = 'file';

    /**
     * Attribute of the annotation tag.
     */
    const DESTINATION = 'destination';

    /**
     * Attribute of the annotation tag.
     */
    const PAGE = 'page';

    /**
     * Attribute of the annotation tag.
     */
    const NAMED = 'named';

    /**
     * Attribute of the annotation tag.
     */
    const APPLICATION = 'application';

    /**
     * Attribute of the annotation tag.
     */
    const PARAMETERS = 'parameters';

    /**
     * Attribute of the annotation tag.
     */
    const OPERATION = 'operation';

    /**
     * Attribute of the annotation tag.
     */
    const DEFAULTDIR = 'defaultdir';

    /**
     * Attribute of the annotation tag.
     */
    const LLX = 'llx';

    /**
     * Attribute of the annotation tag.
     */
    const LLY = 'lly';

    /**
     * Attribute of the annotation tag.
     */
    const URX = 'urx';

    /**
     * Attribute of the annotation tag.
     */
    const URY = 'ury';

    /**
     * Attribute of the annotation tag.
     */
    const CONTENT = 'content';

    // alignment attribute values

    /**
     * The possible value of an alignment attribute.
     */
    const ALIGN_LEFT = 'Left';

    /**
     * The possible value of an alignment attribute.
     */
    const ALIGN_CENTER = 'Center';

    /**
     * The possible value of an alignment attribute.
     */
    const ALIGN_RIGHT = 'Right';

    /**
     * The possible value of an alignment attribute.
     */
    const ALIGN_JUSTIFIED = 'Justify';

    /**
     * The possible value of an alignment attribute.
     */
    const ALIGN_JUSTIFIED_ALL = 'JustifyAll';

    /**
     * The possible value of an alignment attribute.
     */
    const ALIGN_TOP = 'Top';

    /**
     * The possible value of an alignment attribute.
     */
    const ALIGN_MIDDLE = 'Middle';

    /**
     * The possible value of an alignment attribute.
     */
    const ALIGN_BOTTOM = 'Bottom';

    /**
     * The possible value of an alignment attribute.
     */
    const ALIGN_BASELINE = 'Baseline';

    /**
     * The possible value of an alignment attribute.
     */
    const DEFAULT = 'Default';

    /**
     * The possible value of an alignment attribute.
     */
    const UNKNOWN = 'unknown';

    /**
     * The possible value of an alignment attribute.
     */
    const FONT = 'font';

    /**
     * The possible value of an alignment attribute.
     */
    const SIZE = 'size';

    /**
     * The possible value of an alignment attribute.
     */
    const STYLE = 'fontstyle';

    /**
     * The possible value of a tag.
     */
    const HORIZONTALRULE = 'horizontalrule';

    /**
     * The possible value of a tag.
     */
    const PAGE_SIZE = 'pagesize';

    /**
     * The possible value of a tag.
     */
    const ORIENTATION = 'orientation';

    /**
     * A possible list attribute.
     */
    const ALIGN_INDENTATION_ITEMS = 'alignindent';

    /**
     * A possible list attribute.
     */
    const AUTO_INDENT_ITEMS = 'autoindent';

    /**
     * A possible list attribute.
     */
    const LOWERCASE = 'lowercase';

    /**
     * A possible list attribute.
     *
     * @since 2.1.3
     */
    const FACE = 'face';

    /**
     * Attribute of the image or iframe tag.
     *
     * @since 2.1.3
     */
    const SRC = 'src';

    // methods

    /**
     * Translates the alignment value to a String value.
     *
     * @param int $alignment the alignment value
     *
     * @return int
     */
    public static function getAlignment(int $alignment) {
        switch ($alignment) {
            case CDocument_Pdf_ElementConstant::ALIGN_LEFT:
                return self::ALIGN_LEFT;
            case CDocument_Pdf_ElementConstant::ALIGN_CENTER:
                return self::ALIGN_CENTER;
            case CDocument_Pdf_ElementConstant::ALIGN_RIGHT:
                return self::ALIGN_RIGHT;
            case CDocument_Pdf_ElementConstant::ALIGN_JUSTIFIED:
            case CDocument_Pdf_ElementConstant::ALIGN_JUSTIFIED_ALL:
                return self::ALIGN_JUSTIFIED;
            case CDocument_Pdf_ElementConstant::ALIGN_TOP:
                return self::ALIGN_TOP;
            case CDocument_Pdf_ElementConstant::ALIGN_MIDDLE:
                return self::ALIGN_MIDDLE;
            case CDocument_Pdf_ElementConstant::ALIGN_BOTTOM:
                return self::ALIGN_BOTTOM;
            case CDocument_Pdf_ElementConstant::ALIGN_BASELINE:
                return self::ALIGN_BASELINE;
            default:
                return self::DEFAULT;
        }
    }

    /**
     * Translates a String value to an alignment value. (written by Norman Richards, integrated into iText by Bruno)
     * return an alignment value (one of the ALIGN_ constants of the Element interface).
     *
     * @param string $alignment a String (one of the ALIGN_ constants of this class)
     *
     * @return int
     */
    public static function alignmentValue(string $alignment) {
        if ($alignment == null) {
            return CDocument_Pdf_ElementConstant::ALIGN_UNDEFINED;
        }
        if (cstr::lower(self::ALIGN_CENTER) == cstr::lower($alignment)) {
            return CDocument_Pdf_ElementConstant::ALIGN_CENTER;
        }
        if (cstr::lower(self::ALIGN_LEFT) == cstr::lower($alignment)) {
            return CDocument_Pdf_ElementConstant::ALIGN_LEFT;
        }
        if (cstr::lower(self::ALIGN_RIGHT) == cstr::lower($alignment)) {
            return CDocument_Pdf_ElementConstant::ALIGN_RIGHT;
        }
        if (cstr::lower(self::ALIGN_JUSTIFIED) == cstr::lower($alignment)) {
            return CDocument_Pdf_ElementConstant::ALIGN_JUSTIFIED;
        }
        if (cstr::lower(self::ALIGN_JUSTIFIED_ALL) == cstr::lower($alignment)) {
            return CDocument_Pdf_ElementConstant::ALIGN_JUSTIFIED_ALL;
        }
        if (cstr::lower(self::ALIGN_TOP) == cstr::lower($alignment)) {
            return CDocument_Pdf_ElementConstant::ALIGN_TOP;
        }
        if (cstr::lower(self::ALIGN_MIDDLE) == cstr::lower($alignment)) {
            return CDocument_Pdf_ElementConstant::ALIGN_MIDDLE;
        }
        if (cstr::lower(self::ALIGN_BOTTOM) == cstr::lower($alignment)) {
            return CDocument_Pdf_ElementConstant::ALIGN_BOTTOM;
        }
        if (cstr::lower(self::ALIGN_BASELINE) == cstr::lower($alignment)) {
            return CDocument_Pdf_ElementConstant::ALIGN_BASELINE;
        }

        return CDocument_Pdf_ElementConstant::ALIGN_UNDEFINED;
    }
}
