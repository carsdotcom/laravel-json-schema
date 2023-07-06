<?php
/**
 * Test the ValidatesWithJsonSchema trait, which is used before saving in JsonModel
 * and can be used before emitting in ApiController->validatedJson()
 */
declare(strict_types=1);

namespace Tests\Unit\Traits;

use Carsdotcom\JsonSchemaValidation\Exceptions\JsonSchemaValidationException;
use Carsdotcom\JsonSchemaValidation\Traits\ValidatesWithJsonSchema;
use Tests\BaseTestCase;

/**
 * Class ValidatesWithJsonSchemaTest
 * @package Tests\Unit\Traits
 */
class ValidatesWithJsonSchemaTest extends BaseTestCase
{
    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('json-schema.base_url', 'https://schemas.dealerinspire.com/online-shopper/');
        $app['config']->set('json-schema.local_base_prefix', dirname(__FILE__) . '/../../../tests/Schemas');
        $app['config']->set('json-schema.local_base_prefix_tests', dirname(__FILE__) . '/../../../tests/Schemas');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    public function testValidateSucceedsImmediatelyIfSchemaUndefined(): void
    {
        $traitUser = new class {
            use ValidatesWithJsonSchema;
        };
        self::assertTrue($traitUser->validateOrThrow());
    }

    /**
     * This is a pretty bad idea, that just happens to work because of the ->validate signature in opis/json-schema
     * Using a URI (like below) makes the schemas reusable in our OpenAPI documentation,
     * and composable like in CollectionOfJsonModels
     */
    public function testValidDataSchema(): void
    {
        $traitUser = new class {
            use ValidatesWithJsonSchema;
            public const SCHEMA = '{"properties":{"value":{"type":"number"}}}';

            /** @var int */
            public $value;
        };
        $traitUser->value = 3;
        self::assertTrue($traitUser->validateOrThrow());

        $traitUser->value = "Jack's Pizza";
        $this->expectException(JsonSchemaValidationException::class);
        $this->expectExceptionMessage('Anonymous Class contains invalid data!');
        $traitUser->validateOrThrow();
    }

    public function testValidUriSchema(): void
    {
        $vehicle = new class {
            use ValidatesWithJsonSchema;
            /** @var string JsonSchema URI (relative to app/Schemas) that can be used to validate this JsonModel */
            public const SCHEMA = 'vehicle.json';
        };
        $vehicle->vin = '11111111111111111';
        self::assertTrue($vehicle->validateOrThrow());

        $vehicle->vin = 100; // Expected a string matching the vin schema
        $this->expectException(JsonSchemaValidationException::class);
        $this->expectExceptionMessage('Anonymous Class contains invalid data!');
        $vehicle->validateOrThrow();
    }
}
