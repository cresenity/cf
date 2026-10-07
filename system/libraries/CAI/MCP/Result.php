<?php

/**
 * Hasil pemanggilan tool.
 */
class CAI_MCP_Result {
    /**
     * @var array
     */
    protected $content;

    /**
     * @var bool
     */
    protected $isError;

    /**
     * @param array $content
     * @param bool  $isError
     */
    public function __construct(array $content, $isError = false) {
        $this->content = $content;
        $this->isError = (bool) $isError;
    }

    /**
     * @param string $text
     *
     * @return CAI_MCP_Result
     */
    public static function text($text) {
        return new static([['type' => 'text', 'text' => (string) $text]]);
    }

    /**
     * @param mixed $data
     *
     * @return CAI_MCP_Result
     */
    public static function json($data) {
        return static::text(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param string $text
     *
     * @return CAI_MCP_Result
     */
    public static function error($text) {
        return new static([['type' => 'text', 'text' => (string) $text]], true);
    }

    /**
     * @return array
     */
    public function toArray() {
        return ['content' => $this->content, 'isError' => $this->isError];
    }
}
