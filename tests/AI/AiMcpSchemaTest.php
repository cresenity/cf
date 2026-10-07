<?php

use PHPUnit\Framework\TestCase;

/**
 * CAI_MCP_Schema: subset JSON Schema untuk argumen tool.
 */
class AiMcpSchemaTest extends TestCase {
    public function testTypesAreChecked() {
        $this->assertSame([], CAI_MCP_Schema::validate(['type' => 'string'], 'x'));
        $this->assertNotSame([], CAI_MCP_Schema::validate(['type' => 'string'], 5));
        $this->assertSame([], CAI_MCP_Schema::validate(['type' => 'integer'], 5));
        $this->assertSame([], CAI_MCP_Schema::validate(['type' => 'integer'], 5.0), 'JSON mengenal 5.0 sebagai integer');
        $this->assertNotSame([], CAI_MCP_Schema::validate(['type' => 'integer'], 5.5));
        $this->assertNotSame([], CAI_MCP_Schema::validate(['type' => 'integer'], true), 'boolean bukan angka');
        $this->assertSame([], CAI_MCP_Schema::validate(['type' => 'number'], 1.5));
        $this->assertSame([], CAI_MCP_Schema::validate(['type' => ['string', 'null']], null));
        $this->assertSame([], CAI_MCP_Schema::validate(['type' => 'boolean'], false));
    }

    public function testArrayVersusObject() {
        $this->assertSame([], CAI_MCP_Schema::validate(['type' => 'array'], [1, 2]));
        $this->assertNotSame([], CAI_MCP_Schema::validate(['type' => 'array'], ['a' => 1]));
        $this->assertSame([], CAI_MCP_Schema::validate(['type' => 'object'], ['a' => 1]));
        $this->assertSame([], CAI_MCP_Schema::validate(['type' => 'object'], []), 'objek kosong');
        $this->assertNotSame([], CAI_MCP_Schema::validate(['type' => 'object'], [1, 2]));
    }

    public function testRequiredAndNestedProperties() {
        $schema = ['type' => 'object', 'required' => ['a'], 'properties' => ['a' => ['type' => 'string'], 'b' => ['type' => 'object', 'required' => ['c'], 'properties' => ['c' => ['type' => 'integer']]]]];
        $this->assertSame([], CAI_MCP_Schema::validate($schema, ['a' => 'x']));
        $this->assertSame(['a wajib diisi'], CAI_MCP_Schema::validate($schema, []));
        $errors = CAI_MCP_Schema::validate($schema, ['a' => 'x', 'b' => ['c' => 'bukan angka']]);
        $this->assertSame(['b.c harus bertipe integer'], $errors);
        $this->assertSame(['b.c wajib diisi'], CAI_MCP_Schema::validate($schema, ['a' => 'x', 'b' => ['z' => 1]]));
    }

    public function testEnumLengthAndRange() {
        $this->assertNotSame([], CAI_MCP_Schema::validate(['enum' => ['a', 'b']], 'c'));
        $this->assertSame([], CAI_MCP_Schema::validate(['enum' => ['a', 'b']], 'a'));
        $this->assertNotSame([], CAI_MCP_Schema::validate(['type' => 'string', 'minLength' => 3], 'ab'));
        $this->assertNotSame([], CAI_MCP_Schema::validate(['type' => 'string', 'maxLength' => 2], 'abc'));
        $this->assertSame([], CAI_MCP_Schema::validate(['type' => 'string', 'maxLength' => 2], 'äö'), 'dihitung per karakter');
        $this->assertNotSame([], CAI_MCP_Schema::validate(['type' => 'integer', 'minimum' => 1], 0));
        $this->assertNotSame([], CAI_MCP_Schema::validate(['type' => 'integer', 'maximum' => 1], 2));
    }

    public function testItemsAreValidatedWithIndexInPath() {
        $errors = CAI_MCP_Schema::validate(['type' => 'array', 'items' => ['type' => 'integer']], [1, 'x', 3]);
        $this->assertSame(['arguments[1] harus bertipe integer'], $errors);
    }
}
