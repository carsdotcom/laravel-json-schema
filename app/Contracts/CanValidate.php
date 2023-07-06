<?php

/**
 * Handy interface for "Does this implement ValidatesWithJsonSchema"
 */

namespace Carsdotcom\JsonSchemaValidation\Contracts;

use Exception;

/**
 * Interface CanValidate
 * @package Carsdotcom\JsonSchemaValidation\Contracts
 */
interface CanValidate
{
    /**
     * Does this object pass its own standard for validation?
     * @return true
     * @throws Exception if the data is invalid. Exact exception is up to the implementation,
     * but should implement HasExtendedExceptionData like JsonSchemaValidationException
     */
    public function validateOrThrow(string $exceptionMessage = null): bool;
}
