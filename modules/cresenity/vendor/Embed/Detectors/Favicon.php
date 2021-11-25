<?php

//declare(strict_types = 1);

namespace Embed\Detectors;

use Psr\Http\Message\UriInterface;

class Favicon extends Detector {
    public function detect() {
        $document = $this->extractor->getDocument();

        $link = $document->link('shortcut icon') ?: $document->link('icon') ?: $this->extractor->getUri()->withPath('/favicon.ico')->withQuery('');
        return $link;
    }
}
