<?php
/*
 * This file is part of phpunit/php-code-coverage.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace SebastianBergmann\CodeCoverage\Report\Xml;

use DOMDocument;
use DOMElement;

/**
 * @internal This class is not covered by the backward compatibility promise for phpunit/php-code-coverage
 */
abstract class Node
{
    /**
     * @var DOMDocument
     */
    private $dom;

    /**
     * @var DOMElement
     */
    private $contextNode;

    public function __construct(DOMElement $context)
    {
        $this->setContextNode($context);
    }

    public function dom()
    {
        return $this->dom;
    }

    public function totals()
    {
        $totalsContainer = $this->contextNode()->firstChild;

        if (!$totalsContainer) {
            $totalsContainer = $this->contextNode()->appendChild(
                $this->dom->createElementNS(
                    'https://schema.phpunit.de/coverage/1.0',
                    'totals'
                )
            );
        }

        return new Totals($totalsContainer);
    }

    public function addDirectory($name)
    {
        $dirNode = $this->dom()->createElementNS(
            'https://schema.phpunit.de/coverage/1.0',
            'directory'
        );

        $dirNode->setAttribute('name', $name);
        $this->contextNode()->appendChild($dirNode);

        return new Directory($dirNode);
    }

    public function addFile($name, $href)
    {
        $fileNode = $this->dom()->createElementNS(
            'https://schema.phpunit.de/coverage/1.0',
            'file'
        );

        $fileNode->setAttribute('name', $name);
        $fileNode->setAttribute('href', $href);
        $this->contextNode()->appendChild($fileNode);

        return new File($fileNode);
    }

    protected function setContextNode(DOMElement $context)
    {
        $this->dom         = $context->ownerDocument;
        $this->contextNode = $context;
    }

    protected function contextNode()
    {
        return $this->contextNode;
    }
}
