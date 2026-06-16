<?php
/**
 * Tests for the SchemaValidator service.
 */
declare(strict_types=1);

namespace Tests\Feature;

use Carsdotcom\JsonSchemaValidation\Exceptions\JsonSchemaValidationException;
use Carsdotcom\JsonSchemaValidation\SchemaValidatorService;
use Illuminate\Support\Facades\Config;
use Opis\JsonSchema\Exceptions\UnresolvedReferenceException;
use Opis\JsonSchema\Helper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\BaseTestCase;
use Tests\Mocks\Models\Vehicle;

/**
 * Class SchemaValidatorTest
 * @package Tests\Feature
 */
class SchemaValidatorServiceTest extends BaseTestCase
{
    public function testValidateUriSchema(): void
    {
        $data = Vehicle::factory()->make();

        $validator = new SchemaValidatorService();

        self::assertTrue($validator->validate($data, Vehicle::SCHEMA));
        unset($data['vin']);
        self::assertFalse($validator->validate($data, Vehicle::SCHEMA));
    }

    public function testValidateObjectSchema(): void
    {
        $validator = new SchemaValidatorService();
        $schema = (object) ['type' => 'number', 'minimum' => 69];

        self::assertTrue($validator->validate(420, $schema));
        self::assertFalse($validator->validate(42, $schema));
    }

    public function testValidateStringSchema(): void
    {
        $validator = new SchemaValidatorService();
        $schema = '{"type": "number", "minimum": 69}';

        self::assertTrue($validator->validate(420, $schema));
        self::assertFalse($validator->validate(42, $schema));
    }

    /**
     * The Opis library only wants to work with PHP objects, which both isn't what Laravel uses and isn't our code style.
     * This normalizer has the added benefit of exercising JsonSerializable contract (so data doesn't have to serialize itself before calling) which does things like flatten Collections to simple JSON arrays.
     * @param $data
     * @param $schema
     * @dataProvider normalizeDataProvider
     */
    #[DataProvider('normalizeDataProvider')]
    public function testNormalizeData($data, $schema): void
    {
        $validator = new SchemaValidatorService();
        self::assertTrue($validator->validate($data, $schema));
    }

    public static function normalizeDataProvider(): array
    {
        return [
            'Collection becomes array' => [collect([1, 2, 3]), '{"type":"array","minItems":3}'],
            'real array unmodified' => [[1, 2, 3], '{"type":"array","minItems":3}'],
            'Assoc array becomes object' => [['a' => 1], '{"type":"object","properties":{"a":{"type":"number"}}}'],
            'real object unmodified' => [(object) ['a' => 1], '{"type":"object","properties":{"a":{"type":"number"}}}'],
        ];
    }

    public function testRegisterRawSchema(): void
    {
        $rawSchema = '{"type": "array", "items": {"$ref": "vin.json"}}';
        $vins = ['11111111111111111', '22222222222222222'];
        $validator = new SchemaValidatorService();
        try {
            $validator->validate($vins, $rawSchema);
            self::fail('Should have thrown exception');
        } catch (UnresolvedReferenceException $e) {
            // raw schema contains relative URIs, we need to register it so Opis understands where the root is
            self::assertSame('Unresolved reference: schema:///vin.json#', $e->getMessage());
        }

        $absoluteRaw = $validator->registerRawSchema($rawSchema);
        self::assertStringStartsWith(Config::get('json-schema.base_url'), $absoluteRaw);
        self::assertTrue($validator->validate($vins, $absoluteRaw));
    }

    /**
     * @dataProvider provideValidateEncodedStringOrThrow
     */
    #[DataProvider('provideValidateEncodedStringOrThrow')]
    public function testValidateEncodedStringOrThrow(string $encodedData, mixed $schema, bool $expectedSuccess): void
    {
        $validator = new SchemaValidatorService();

        try {
            self::assertTrue($validator->validateEncodedStringOrThrow($encodedData, $schema));
            if (!$expectedSuccess) {
                self::fail("Should have thrown JsonSchemaValidationException");
            }
        } catch (JsonSchemaValidationException $e) {
            if ($expectedSuccess) {
                self::assertTrue(false, "Expected success, instead got " . $e->errorsAsMultilineString());
            } else {
                self::addToAssertionCount(1);
            }
        }
    }

    public static function provideValidateEncodedStringOrThrow(): array
    {
        return [
            'primitive, string schema' => ['420', '{"type": "number", "minimum": 69}', true],
            'primitive, string schema fails' => ['42', '{"type": "number", "minimum": 69}', false],
            'empty object is still an object' => ['{}', '{"type": "object"}', true],
            'empty object is not an array' => ['{}', '{"type": "array"}', false],
            'typical complex object, success' => ['{"a":1}', '{"type":"object","properties":{"a":{"type":"number"}}, "required":["a"]}', true],
            'typical complex object, failure' => ['{"b":1}', '{"type":"object","properties":{"a":{"type":"number"}}, "required":["a"]}', false],
        ];
    }

    /**
     * The normalizeWithJsonSerializable: false fast path (Opis Helper::toJSON instead of a
     * json_encode/json_decode round-trip) must still convert associative arrays to objects so they
     * validate against object schemas, leave lists as arrays, and pass scalars through.
     * @param $data
     * @param $schema
     * @dataProvider plainArrayNormalizeProvider
     */
    #[DataProvider('plainArrayNormalizeProvider')]
    public function testValidateWithPlainArrayNormalization($data, $schema): void
    {
        $validator = new SchemaValidatorService();
        self::assertTrue($validator->validate($data, $schema, normalizeWithJsonSerializable: false));
    }

    public static function plainArrayNormalizeProvider(): array
    {
        return [
            'assoc array becomes object' => [
                ['a' => 1],
                '{"type":"object","properties":{"a":{"type":"number"}},"required":["a"]}',
            ],
            'list stays array' => [
                [1, 2, 3],
                '{"type":"array","minItems":3}',
            ],
            'real object unmodified' => [
                (object) ['a' => 1],
                '{"type":"object","properties":{"a":{"type":"number"}}}',
            ],
            'nested assoc within list becomes objects' => [
                ['items' => [['x' => 1], ['x' => 2]]],
                '{"type":"object","properties":{"items":{"type":"array","items":{"type":"object","properties":{"x":{"type":"number"}},"required":["x"]}}}}',
            ],
            'empty array stays array' => [
                [],
                '{"type":"array"}',
            ],
            'scalar passes through' => [
                420,
                '{"type":"number","minimum":69}',
            ],
        ];
    }

    /**
     * Safety net for the normalizeWithJsonSerializable: false opt-out: for data composed solely of
     * arrays, scalars, and stdClass, Helper::toJSON must produce a structure IDENTICAL to the
     * json_encode/json_decode round-trip.
     * @param $data
     * @dataProvider plainDataEquivalenceProvider
     */
    #[DataProvider('plainDataEquivalenceProvider')]
    public function testPlainDataNormalizationMatchesRoundTrip($data): void
    {
        $validator = new SchemaValidatorService();
        $normalizeData = new \ReflectionMethod($validator, 'normalizeData');

        self::assertEquals(
            $normalizeData->invoke($validator, $data, true),   // round-trip
            $normalizeData->invoke($validator, $data, false),  // Helper::toJSON
        );
    }

    public static function plainDataEquivalenceProvider(): array
    {
        return [
            'scalar int' => [420],
            'scalar float' => [4.2],
            'scalar string' => ['hello'],
            'bool' => [true],
            'null' => [null],
            'list' => [[1, 2, 3]],
            'assoc' => [['a' => 1, 'b' => 'two']],
            'empty array' => [[]],
            'stdClass' => [(object) ['a' => 1]],
            'incentives-shaped offers' => [[
                'abc123hash' => [
                    'title' => '2026 Toyota Camry',
                    'vehicle' => (object) ['vin' => 'JT1234567890', 'year' => '2026', 'msrp' => 30000],
                    'cash' => ['total' => 500, 0 => ['program_id' => '1', 'total_cash' => 500.0]],
                    'finance_all' => ['md5key' => ['terms' => [['length' => '60', 'rate' => '1.9']]]],
                    'lease_all' => [],
                    'specials' => [],
                    'valid_vins' => ['JT1234567890', 'JT1234567891'],
                    'disclaimers' => [],
                ],
            ]],
        ];
    }

    /**
     * Objects whose JSON form comes from jsonSerialize() (e.g. a laravel-json-model) MUST use the
     * default round-trip. Helper::toJSON reads public properties and ignores jsonSerialize(), so
     * normalizeWithJsonSerializable: false would validate the wrong shape — which is exactly why the
     * opt-out is unsafe for anything but plain arrays/scalars/stdClass.
     */
    public function testJsonSerializableRequiresRoundTripNormalization(): void
    {
        // Mirrors how a JsonModel exposes data via jsonSerialize() rather than public properties,
        // without taking a circular test dependency on carsdotcom/laravel-json-model.
        $serializable = new class implements \JsonSerializable {
            public string $model = 'Chevrolet';

            public function jsonSerialize(): mixed
            {
                return ['vin' => '11111111111111111'];
            }
        };

        self::assertEquals((object) ['model' => 'Chevrolet'], Helper::toJSON($serializable));
        self::assertEquals((object) ['vin' => '11111111111111111'], json_decode(json_encode($serializable)));

        $validator = new SchemaValidatorService();
        // Default honors jsonSerialize() -> {"vin": ...} -> a valid Vehicle.
        self::assertTrue($validator->validate($serializable, 'vehicle.json'));
        // Opt-out sees only the public {model} property (no vin) -> invalid.
        self::assertFalse($validator->validate($serializable, 'vehicle.json', normalizeWithJsonSerializable: false));
    }

}
