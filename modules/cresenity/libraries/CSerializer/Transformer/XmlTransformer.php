<?php

/**
 * Class XmlTransformer.
 */
class CSerializer_Transformer_XmlTransformer extends CSerializer_Transformer_ArrayTransformer {
    /**
     * @param mixed $value
     *
     * @return string
     */
    public function serialize($value) {
        $array = parent::serialize($value);

        $xmlData = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><data></data>');
        $this->arrayToXml($array, $xmlData);
        $xml = $xmlData->asXML();

        $xmlDoc = new DOMDocument();
        $xmlDoc->loadXML($xml);
        $xmlDoc->preserveWhiteSpace = false;
        $xmlDoc->formatOutput = true;

        return $xmlDoc->saveXML();
    }

    /**
     * Converts an array to XML using SimpleXMLElement.
     *
     * @param array            $data
     * @param SimpleXMLElement $xmlData
     */
    private function arrayToXml(array &$data, SimpleXMLElement $xmlData) {
        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                if (\is_numeric($key)) {
                    $key = 'sequential-item';
                }
                $subnode = $xmlData->addChild($key);

                $this->arrayToXml($value, $subnode);
            } else {
                $subnode = $xmlData->addChild("$key", "$value");

                $type = \gettype($value);
                if ('array' !== $type) {
                    $subnode->addAttribute('type', $type);
                }
            }
        }
    }
}
