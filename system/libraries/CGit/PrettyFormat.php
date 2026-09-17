<?php

class CGit_PrettyFormat {
    public function escapeXml($output) {
        return preg_replace('/[\x00-\x1f]/', '?', $output);
    }

    public function parse($output) {
        if (empty($output)) {
            throw new \RuntimeException('No data available');
        }

        try {
            $xml = new \SimpleXmlIterator("<data>${output}</data>");
        } catch (\Exception $e) {
            $output = $this->escapeXml($output);
            $xml = new \SimpleXmlIterator("<data>${output}</data>");
        }

        $data = $this->iteratorToArray($xml);

        return $data['item'];
    }

    protected function iteratorToArray($iterator) {
        foreach ($iterator as $key => $item) {
            if ($iterator->hasChildren()) {
                $data[$key][] = $this->iteratorToArray($item);

                continue;
            }

            $data[$key] = trim(strval($item));
        }

        return $data;
    }
}
