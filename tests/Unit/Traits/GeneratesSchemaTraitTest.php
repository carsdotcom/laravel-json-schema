<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use Carsdotcom\JsonSchemaValidation\Traits\GeneratesSchemaTrait;
use DomainException;
use Illuminate\Support\Facades\Storage;
use Tests\BaseTestCase;
use Tests\Mocks\Enums\TestBackedEnum;
use Tests\Mocks\Enums\TestBackedEnumNoSchema;
use Tests\Mocks\Enums\TestIntBackedEnum;

class GeneratesSchemaTraitTest extends BaseTestCase
{
    public function testGenerateSchemaThrowsWithoutSchemaConstant(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("can't generate a schema; the SCHEMA class constant is undefined");
        TestBackedEnumNoSchema::generateSchema();
    }

    /**
     * Uses a named fixture class (not anonymous) so the schema title and description
     * reflect a stable, readable class name.
     */
    public function testGenerateSchemaWritesCorrectStructureForStringBackedEnum(): void
    {
        Storage::fake('schemas');

        TestBackedEnum::generateSchema();

        Storage::disk('schemas')->assertExists('test/backed-enum.json');

        $schema = json_decode(Storage::disk('schemas')->get('test/backed-enum.json'), true);
        self::assertSame('http://json-schema.org/draft-07/schema#', $schema['$schema']);
        self::assertSame('string', $schema['type']);
        self::assertSame(['foo', 'bar', 'baz'], $schema['enum']);

        self::assertSame(<<<'JSON'
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "title": "Test Backed Enum",
    "description": "Enumerated values for Test Backed Enum. Note this schema is automatically generated from Tests\\Mocks\\Enums\\TestBackedEnum, DO NOT modify by hand.",
    "enum": [
        "foo",
        "bar",
        "baz"
    ],
    "type": "string"
}

JSON, Storage::disk('schemas')->get('test/backed-enum.json'));
    }

    public function testGenerateSchemaWritesCorrectStructureForIntBackedEnum(): void
    {
        Storage::fake('schemas');

        TestIntBackedEnum::generateSchema();

        Storage::disk('schemas')->assertExists('test/int-backed-enum.json');

        $schema = json_decode(Storage::disk('schemas')->get('test/int-backed-enum.json'), true);
        self::assertSame('int', $schema['type']);
        self::assertSame([1, 2], $schema['enum']);

        self::assertSame(<<<'JSON'
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "title": "Test Int Backed Enum",
    "description": "Enumerated values for Test Int Backed Enum. Note this schema is automatically generated from Tests\\Mocks\\Enums\\TestIntBackedEnum, DO NOT modify by hand.",
    "enum": [
        1,
        2
    ],
    "type": "int"
}

JSON, Storage::disk('schemas')->get('test/int-backed-enum.json'));
    }
}
