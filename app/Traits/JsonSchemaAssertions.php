<?php
/**
 * Helper assertions to be used in conjunction with PhpUnit
 */
declare(strict_types=1);

namespace Carsdotcom\JsonSchemaValidation\Traits;


use Carsdotcom\JsonSchemaValidation\Contracts\CanValidate;
use Carsdotcom\JsonSchemaValidation\Exceptions\JsonSchemaValidationException;
use Carsdotcom\JsonSchemaValidation\SchemaValidator;

trait JsonSchemaAssertions
{
    /**
     * The passed Object is NOT valid for the passed Json Schema
     *
     * @param string $schemaUri
     * @param mixed $object
     * @param string|null $message
     * @return void
     */
    public static function assertInvalidForSchema(string $schemaUri, $object, ?string $message = ''): void
    {
        static::assertThat(SchemaValidator::validate($object, $schemaUri), static::isFalse(), $message);
    }

    /**
     * The passed Object validates (according to its implementation of the CanValidate contract)
     */
    public static function assertValid(CanValidate $model): void
    {
        try {
            self::assertTrue($model->validateOrThrow());
        } catch (JsonSchemaValidationException $e) {
            self::assertCanonicallySame([], $e->errors(), 'Validation failed with errors.');
        }
    }


}
