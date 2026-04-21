<?php
/**
 * Generate JsonSchema files automatically for anything in the App\Enums namespace
 * that implements the trait GeneratesSchema
 */

namespace Carsdotcom\JsonSchemaValidation\Console\Commands\Schemas;

use Carsdotcom\JsonSchemaValidation\Helpers\FindClasses;
use Carsdotcom\JsonSchemaValidation\Traits\GeneratesSchemaMyCLabsTrait;
use Carsdotcom\JsonSchemaValidation\Traits\GeneratesSchemaTrait;
use Carsdotcom\JsonSchemaValidation\Traits\VerboseLineTrait;
use Illuminate\Console\Command;

class Generate extends Command
{
    use VerboseLineTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schemas:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Json Schema files';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $schemaTraits = [GeneratesSchemaTrait::class, GeneratesSchemaMyCLabsTrait::class];

        FindClasses::inAppPath('Enums')
            ->filter(fn($class) => array_intersect($schemaTraits, class_uses_recursive($class)))
            ->each(fn($class) => $class::generateSchema());
    }
}