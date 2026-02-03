<?php
/**
 * Helper assertions to be used in conjunction with PhpUnit
 */
declare(strict_types=1);

namespace Carsdotcom\JsonSchemaValidation\Traits;

use Carsdotcom\JsonSchemaValidation\Contracts\CanValidate;
use Carsdotcom\JsonSchemaValidation\Exceptions\JsonSchemaValidationException;
use Carsdotcom\JsonSchemaValidation\Helpers\Json;
use Carsdotcom\JsonSchemaValidation\SchemaValidator;
use PHPUnit\Framework\Assert;

/**
 * @mixin Assert  This trait should be added to PHPUnit test classes, usually BaseTestCase
 */
trait JsonSchemaAssertions
{
    /**
     * Given two things that support JSON encoding,
     * assert that they are identical in their canonicalized (sorted props), stringified form
     * @param mixed $a literally anything that can be JSON encoded
     * @param mixed $b literally anything that can be JSON encoded
     */
    public static function assertCanonicallySame(mixed $a, mixed $b, string $comment = ''): void
    {
        $cannonA = Json::canonicalize(json_encode($a), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $cannonB = Json::canonicalize(json_encode($b), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        self::assertSame($cannonA, $cannonB, $comment);
    }

    public function assertCanonicallySameExcept($a, $b, array $ignoredKeys, string $comment = ''): void
    {
        $a = collect($a)->except($ignoredKeys)->toArray();
        $b = collect($b)->except($ignoredKeys)->toArray();
        self::assertCanonicallySame($a, $b, $comment);
    }

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
