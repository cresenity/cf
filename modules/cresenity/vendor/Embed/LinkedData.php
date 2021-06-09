<?php

namespace Embed;

use Exception;
use ML\JsonLD\Document as LdDocument;
use ML\JsonLD\DocumentInterface;
use ML\JsonLD\GraphInterface;
use ML\JsonLD\Node;
use Throwable;

class LinkedData {
    use ApiTrait;

    private $document;

    private function get(...$keys) {
        $graph = $this->getGraph();

        if (!$graph) {
            return null;
        }

        foreach ($graph->getNodes() as $node) {
            $value = self::getValue($node, ...$keys);

            if ($value) {
                return $value;
            }
        }

        return null;
    }

    private function getGraph($name = null) {
        if (!isset($this->document)) {
            try {
                $this->document = LdDocument::load(json_encode($this->all()));
            } catch (Exception $ex) {
                $this->document = LdDocument::load('{}');
                return null;
            }
        }

        return $this->document->getGraph();
    }

    protected function fetchData() {
        $document = $this->extractor->getDocument();
        $content = $document->select('.//script', ['type' => 'application/ld+json'])->str();

        if (empty($content)) {
            return [];
        }

        try {
            return json_decode($content, true) ?: [];
        } catch (Exception $exception) {
            return [];
        }
    }

    private static function getValue(Node $node, ...$keys) {
        foreach ($keys as $key) {
            $node = $node->getProperty("http://schema.org/{$key}");

            if (!$node) {
                return null;
            }
        }

        return self::detectValue($node);
    }

    private static function detectValue($value) {
        if (is_array($value)) {
            return array_map(function ($val) {
                return self::detectValue($val);
            }, array_values($value));
        }

        if (is_scalar($value)) {
            return $value;
        }

        if ($value instanceof Node) {
            return $value->getId();
        }

        return $value->getValue();
    }
}
