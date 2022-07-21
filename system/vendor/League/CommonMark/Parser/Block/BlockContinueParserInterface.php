<?php

/*
 * This file is part of the league/commonmark package.
 *
 * (c) Colin O'Dell <colinodell@gmail.com>
 *
 * Original code based on the CommonMark JS reference parser (https://bitly.com/commonmark-js)
 *  - (c) John MacFarlane
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\CommonMark\Parser\Block;

use League\CommonMark\Parser\Cursor;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Parser\InlineParserEngineInterface;

/**
 * Interface for a block continuation parser.
 *
 * A block continue parser can only handle a single block instance. The current block being parsed is stored within this parser and
 * can be returned once parsing has completed. If you need to parse multiple block continuations, instantiate a new parser for each one.
 */
interface BlockContinueParserInterface {
    /**
     * Return the current block being parsed by this parser.
     *
     * @return AbstractBlock
     */
    public function getBlock();

    /**
     * Return whether we are parsing a container block.
     *
     * @return bool
     */
    public function isContainer();

    /**
     * Return whether we are interested in possibly lazily parsing any subsequent lines.
     *
     * @return bool
     */
    public function canHaveLazyContinuationLines();

    /**
     * Determine whether the current block being parsed can contain the given child block.
     *
     * @return bool
     */
    public function canContain(AbstractBlock $childBlock);

    /**
     * Attempt to parse the given line.
     *
     * @return null|BlockContinue
     */
    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser);

    /**
     * Add the given line of text to the current block.
     *
     * @param mixed $line
     */
    public function addLine($line);

    /**
     * Close and finalize the current block.
     */
    public function closeBlock();

    /**
     * Parse any inlines inside of the current block.
     */
    public function parseInlines(InlineParserEngineInterface $inlineParser);
}
