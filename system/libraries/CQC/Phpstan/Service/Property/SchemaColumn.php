<?php

/** @see https://github.com/psalm/laravel-psalm-plugin/blob/master/src/SchemaColumn.php */
final class CQC_Phpstan_Service_Property_SchemaColumn {
    public string $name;

    public string $readableType;

    public string $writeableType;

    public bool $nullable;

    public $options;

    public function __construct(
        string $name,
        string $readableType,
        bool $nullable = false,
        /** @var array<int, string> */
        ?array $options = null
    ) {
        $this->name = $name;
        $this->readableType = $readableType;
        $this->nullable = $nullable;
        $this->options = $options;
        $this->writeableType = $readableType;
    }
}
