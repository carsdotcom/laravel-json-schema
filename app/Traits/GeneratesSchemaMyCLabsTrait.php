<?php
/**
 * When added to a class that descends from MyCLabs\Enum\Enum,
 * generate a schema file and store it in an appropriate local folder.
 *
 * To generate/refresh, just run
 * bin/artisan schemas:generate -vvv
 */

namespace Carsdotcom\JsonSchemaValidation\Traits;

use Carsdotcom\JsonSchemaValidation\Helpers\FriendlyClassName;
use Carsdotcom\JsonSchemaValidation\SchemaValidator;
use DomainException;
use Illuminate\Support\Facades\Log;

/**
 * @psalm-require-extends \MyCLabs\Enum\Enum
 * @phpstan-require-extends \MyCLabs\Enum\Enum
 */
trait GeneratesSchemaMyCLabsTrait
{
    /**
     * Given a native PHP enum,
     * generate a schema file and store it in an appropriate local folder.
     */
    public static function generateSchema(): void
    {
        if (! defined(static::class.'::SCHEMA')) {
            throw new DomainException(
                static::class." can't generate a schema; the SCHEMA class constant is undefined"
            );
        }

        $schema = [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'title' => (new FriendlyClassName())(static::class),
            'description' => 'Enumerated values for '.
                (new FriendlyClassName())(static::class).
                '. Note this schema is automatically generated from '.
                static::class.
                ', DO NOT modify by hand.',
            'enum' => array_values(array_unique(static::toArray())),
            'type' => 'string',
        ];

        SchemaValidator::putSchemaContents(static::SCHEMA, $schema);
    }

    /**
     * In the case of a MyCLabs enum, class constants *are* enum cases.
     * But we use a class constant SCHEMA for validation and generation.
     * So we need to customize the toArray method to suppress SCHEMA
     * (If this bothers you, it's a great reason to use language-native enums instead, case and const are extremely clear there!)
     * @return array
     */
    public static function toArray(): array
    {
        $array = parent::toArray();
        unset($array['SCHEMA']);
        return $array;

    }
}
