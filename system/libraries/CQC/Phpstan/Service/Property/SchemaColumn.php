<?php

/**
 * @see https://github.com/psalm/laravel-psalm-plugin/blob/master/src/SchemaColumn.php
 */
final class CQC_Phpstan_Service_Property_SchemaColumn {
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $readableType;

    /**
     * @var string
     */
    public $writeableType;

    /**
     * @var bool
     */
    public $nullable;

    /**
     * @var ?array<int, string>
     */
    public $options;

    /**
     * @param string        $name
     * @param string        $readableType
     * @param bool          $nullable
     * @param null|string[] $options
     */
    public function __construct(
        string $name,
        string $readableType,
        bool $nullable = false,
        ?array $options = null
    ) {
        $this->name = $name;
        $this->readableType = $readableType;
        $this->writeableType = $readableType;
        $this->nullable = $nullable;
        $this->options = $options;
    }
}
