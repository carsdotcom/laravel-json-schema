<?php

/**
 * The implementing exception has extra data it would like to apply when being serialized by app/Exceptions/Handler.php
 */

declare(strict_types=1);

namespace Carsdotcom\JsonSchemaValidation\Contracts;

/**
 * Class HasExtendedExceptionData
 * @package Carsdotcom\JsonSchemaValidation\Contracts
 */
interface HasExtendedExceptionData
{
    /**
     * Returns an array of extra properties that can be merged onto the json representation being assembled
     * @return array
     */
    public function getExtendedData(): array;
}
