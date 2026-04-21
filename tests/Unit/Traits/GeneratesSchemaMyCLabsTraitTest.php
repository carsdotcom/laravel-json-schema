<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use Carsdotcom\JsonSchemaValidation\Traits\GeneratesSchemaMyCLabsTrait;
use DomainException;
use Illuminate\Support\Facades\Storage;
use MyCLabs\Enum\Enum;
use Tests\BaseTestCase;
use Tests\Mocks\Enums\TestMyCLabsEnum;

class GeneratesSchemaMyCLabsTraitTest extends BaseTestCase
{
    /**
     * The whole point of toArray() in this trait is to suppress SCHEMA from the list
     * of valid enum values, since MyCLabs treats all class constants as values.
     */
    public function testToArrayExcludesSchemaConstant(): void
    {
        $enum = new class('a') extends Enum {
            use GeneratesSchemaMyCLabsTrait;
            const SCHEMA = 'test.json';
            const A = 'a';
            const B = 'b';
        };

        self::assertSame(['A' => 'a', 'B' => 'b'], $enum::toArray());
    }

    public function testSchemaConstantIsNotAValidEnumValue(): void
    {
        $enum = new class('a') extends Enum {
            use GeneratesSchemaMyCLabsTrait;
            const SCHEMA = 'test.json';
            const A = 'a';
            const B = 'b';
        };

        self::assertFalse($enum::isValid($enum::SCHEMA));
        self::assertTrue($enum::isValid($enum::A));
        self::assertTrue($enum::isValid($enum::B));
    }

    public function testGenerateSchemaThrowsWithoutSchemaConstant(): void
    {
        $enum = new class('a') extends Enum {
            use GeneratesSchemaMyCLabsTrait;
            const A = 'a';
            const B = 'b';
        };

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("can't generate a schema; the SCHEMA class constant is undefined");
        $enum::generateSchema();
    }

    /**
     * Uses a named fixture class (not anonymous) so the schema title and description
     * reflect a stable, readable class name.
     */
    public function testGenerateSchemaWritesCorrectStructure(): void
    {
        Storage::fake('schemas');

        TestMyCLabsEnum::generateSchema();

        Storage::disk('schemas')->assertExists('test/my-clabs-enum.json');

        $schema = json_decode(Storage::disk('schemas')->get('test/my-clabs-enum.json'), true);
        self::assertSame('http://json-schema.org/draft-07/schema#', $schema['$schema']);
        self::assertSame('string', $schema['type']);
        self::assertEqualsCanonicalizing(['foo', 'bar', 'baz'], $schema['enum']);
        // The SCHEMA constant itself must not appear as an enum value
        self::assertNotContains(TestMyCLabsEnum::SCHEMA, $schema['enum']);

        self::assertSame(<<<'JSON'
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "title": "Test My CLabs Enum",
    "description": "Enumerated values for Test My CLabs Enum. Note this schema is automatically generated from Tests\\Mocks\\Enums\\TestMyCLabsEnum, DO NOT modify by hand.",
    "enum": [
        "foo",
        "bar",
        "baz"
    ],
    "type": "string"
}

JSON, Storage::disk('schemas')->get('test/my-clabs-enum.json'));
    }

    public function testGenerateSchemaDeduplicatesValues(): void
    {
        Storage::fake('schemas');

        $enum = new class('a') extends Enum {
            use GeneratesSchemaMyCLabsTrait;
            const SCHEMA = 'test.json';
            const A = 'a';
            const ALSO_A = 'a';
            const B = 'b';
        };

        $enum::generateSchema();

        $schema = json_decode(Storage::disk('schemas')->get('test.json'), true);
        self::assertEqualsCanonicalizing(['a', 'b'], $schema['enum']);
    }
}
