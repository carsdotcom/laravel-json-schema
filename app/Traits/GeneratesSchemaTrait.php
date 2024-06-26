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
use DomainException;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use UnitEnum;

trait GeneratesSchemaTrait
{
    /**
     * Given a PHP Class that descends from MyCLabs\Enum\Enum
     * Generate a schema file and store it in an appropriate local folder.
     */
    public static function generateSchema(): void
    {
        if (! static::isPhpBuiltInEnum() && ! static::isMyCLabsEnum()) {
            throw new DomainException(
                static::class.' must descend from either '.UnitEnum::class.' or '.static::getMyCLabsClass().' to generate a schema'
            );
        }

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
            'enum' => static::generateEnumArray(),
            'type' => 'string',
        ];

        $reflection = new ReflectionClass(static::class);
        $schemaPath = $reflection->getConstant('SCHEMA');
        if (SchemaValidator::putSchemaContents($schemaPath, $schema)) {
            Log::info('Saving Schema', ['schema filename' => $schemaPath, 'success' => true]);
        } else {
            Log::error('Saving Schema', ['schema filename' => $schemaPath, 'success' => false]);
        }

    }

    public static function generateEnumArray(): array
    {
        if (static::isMyCLabsEnum()) {
            return static::generateMyCLabsEnumArray();
        }

        return static::generatePhpBuiltInEnumArray();
    }

    protected static function generatePhpBuiltInEnumArray(): array
    {
        return array_column(self::cases(), 'value') ?: array_column(self::cases(), 'name');
    }

    protected static function generateMyCLabsEnumArray(): array
    {
        $array = parent::toArray();
        unset($array['SCHEMA']);

        return array_values(array_unique($array));
    }

    protected static function isPhpBuiltInEnum(): bool
    {
        return is_a(static::class, UnitEnum::class, true);
    }

    protected static function getMyCLabsClass(): string
    {
        return 'MyCLabs\Enum\Enum';
    }

    protected static function isMyCLabsEnum(): bool
    {
        return is_a(static::class, static::getMyCLabsClass(), true);
    }
}
