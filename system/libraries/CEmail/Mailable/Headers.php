<?php

/**
 * Header tambahan sebuah Mailable: Message-ID, References, dan header teks bebas.
 */
class CEmail_Mailable_Headers {
    /**
     * @var null|string
     */
    public $messageId;

    /**
     * @var string[]
     */
    public $references = [];

    /**
     * @var array
     */
    public $text = [];

    /**
     * @param null|string $messageId
     * @param string[]    $references
     * @param array       $text
     */
    public function __construct($messageId = null, array $references = [], array $text = []) {
        $this->messageId = $messageId;
        $this->references = $references;
        $this->text = $text;
    }

    /**
     * @param string $messageId
     *
     * @return $this
     */
    public function messageId($messageId) {
        $this->messageId = $messageId;

        return $this;
    }

    /**
     * @param string[] $references
     *
     * @return $this
     */
    public function references(array $references) {
        $this->references = array_merge($this->references, $references);

        return $this;
    }

    /**
     * @param string $name
     * @param string $value
     *
     * @return $this
     */
    public function text($name, $value = null) {
        if (is_array($name)) {
            $this->text = array_merge($this->text, $name);
        } else {
            $this->text[$name] = $value;
        }

        return $this;
    }

    /**
     * Nilai header References: setiap id dalam kurung sudut, dipisah spasi.
     *
     * @return string
     */
    public function referencesString() {
        return implode(' ', array_map(function ($id) {
            return '<' . trim((string) $id, '<>') . '>';
        }, $this->references));
    }
}
