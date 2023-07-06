<?php
/**
 * Tests for the SchemaValidator service.
 */
declare(strict_types=1);

namespace Tests\Feature;

use Carsdotcom\JsonSchemaValidation\SchemaValidatorService;
use Illuminate\Support\Facades\Config;
use Opis\JsonSchema\Exceptions\UnresolvedReferenceException;
use Tests\BaseTestCase;
use Tests\Mocks\Models\Vehicle;

/**
 * Class SchemaValidatorTest
 * @package Tests\Feature
 */
class SchemaValidatorServiceTest extends BaseTestCase
{
    public function testValidateUriSchema()
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
    public function testNormalizeData($data, $schema)
    {
        $validator = new SchemaValidatorService();
        self::assertTrue($validator->validate($data, $schema));
    }

    public function normalizeDataProvider()
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
}
