<?php

namespace Carsdotcom\JsonSchemaValidation\Traits;

use Carsdotcom\JsonSchemaValidation\Exceptions\JsonSchemaValidationException;
use Carsdotcom\JsonSchemaValidation\Helpers\FriendlyClassName;
use Carsdotcom\JsonSchemaValidation\SchemaValidator;
use Symfony\Component\HttpFoundation\Response;

trait ValidatesWithJsonSchema
{
    /**
     * Validate that the current data conforms to the schema
     * Because this trait applies to *all* JsonModels, we immediately return true if SCHEMA is missing.
     *      (this is more common than you think, especially in unit tests)
     * @param string|null $exceptionMessage
     * @return true (can't return false, will throw)
     * @throws JsonSchemaValidationException   if data is invalid
     */
    public function validateOrThrow(
        string $exceptionMessage = null,
        int $failureHttpStatusCode = Response::HTTP_BAD_REQUEST,
    ): bool {
        if (!defined(static::class . '::SCHEMA') || !static::SCHEMA) {
            return true;
        }

        $exceptionMessage = $exceptionMessage ?? (new FriendlyClassName())(static::class) . ' contains invalid data!';

        return SchemaValidator::validateOrThrow(
            $this,
            static::SCHEMA,
            $exceptionMessage,
            failureHttpStatusCode: $failureHttpStatusCode,
        );
    }
}
