<?php

/**
 * Facade for the Schema Validator service
 */

declare(strict_types=1);

namespace Carsdotcom\JsonSchemaValidation;

use Illuminate\Support\Facades\Facade;
use Symfony\Component\HttpFoundation\Response;

/**
 * @method static array|object getSchemaContents(string $relativeUri, bool $associative = true)
 * @method static void putSchemaContents(string $relativeUri, array|object $schema)
 * @method static string registerRawSchema(bool|object|string $schema)
 * @method static bool validate(array|object $data, string $schema)
 * @method static bool validateOrThrow(string|array|object $data, string $schema, string $exceptionMessage = null, bool $appendValidationDescriptions = false, int $failureHttpStatusCode = Response::HTTP_BAD_REQUEST)
 */
class SchemaValidator extends Facade
{
    /**
     * @return string
     */
    public static function getFacadeAccessor(): string
    {
        return SchemaValidatorService::class;
    }
}
