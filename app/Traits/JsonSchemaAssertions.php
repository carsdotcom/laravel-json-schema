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
     * The passed Object validates for the passed Json Schema
     *
     * If `$addFormattedErrorToMessage` is set to true, then a detailed description of why
     * the JSON was invalid will be added to the exception message.
     *
     * @param string $schemaUri
     * @param mixed $object
     * @param string $message
     * @return void
     */
    public static function assertValidForSchema(
        string $schemaUri,
               $object,
        string $message = '',
    ): void {
        try {
            self::assertTrue(SchemaValidator::validate($object, $schemaUri), $message);
        } catch (JsonSchemaValidationException $e) {
            self::assertCanonicallySame([], $e->errors(), $message);
        }
    }

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
