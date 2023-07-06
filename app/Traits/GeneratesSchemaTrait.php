<?php
/**
 * When added to a PHP Class that descends from MyCLabs\Enum\Enum
 * Generate a schema file and store it in an appropriate local folder.
 *
 * To generate/refresh, just run
 * bin/artisan schemas:generate -vvv
 */

namespace Carsdotcom\JsonSchemaValidation\Traits;

use Carsdotcom\JsonSchemaValidation\Helpers\FriendlyClassName;
use Carsdotcom\JsonSchemaValidation\SchemaValidator;
use MyCLabs\Enum\Enum;

trait GeneratesSchemaTrait
{
    /**
     * Given a PHP Class that descends from MyCLabs\Enum\Enum
     * Generate a schema file and store it in an appropriate local folder.
     */
    public static function generateSchema(): void
    {
        if (!is_a(static::class, Enum::class, true)) {
            throw new \DomainException(static::class . ' must descend from ' . Enum::class . ' to generate a schema');
        }

        if (!defined(static::class . '::SCHEMA')) {
            throw new \DomainException(static::class . " can't generate a schema, SCHEMA class constant is undefined");
        }

        $schema = [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'title' => (new FriendlyClassName())(static::class),
            'description' =>
                'Enumerated values for ' .
                (new FriendlyClassName())(static::class) .
                '. Note this schema is automatically generated from ' .
                static::class .
                ', DO NOT modify by hand.',
            'enum' => array_values(array_unique(static::toArray())),
            'type' => 'string',
        ];

        SchemaValidator::putSchemaContents(static::SCHEMA, $schema);
    }

    /**
     * Returns all possible values as an array
     *
     * @return array Constant name in key, constant value in value
     */
    public static function toArray(): array
    {
        $array = parent::toArray();
        unset($array['SCHEMA']);
        return $array;
    }
}