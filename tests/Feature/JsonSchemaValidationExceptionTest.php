<?php
/**
 * JsonSchemaValidationException is surprisingly focused on reformatting data to make itself understood.
 * So those formatters need unit tests.
 */
declare(strict_types=1);

namespace Tests\Feature;

use Carsdotcom\JsonSchemaValidation\Exceptions\JsonSchemaValidationException;
use Carsdotcom\JsonSchemaValidation\SchemaValidator;
use Tests\BaseTestCase;

class JsonSchemaValidationExceptionTest extends BaseTestCase
{
    public function testErrorsAsMultilineString(): void
    {
        try {
            SchemaValidator::validateOrThrow(
                ['foo' => 1, 'bar' => 'two'],
                '{"type":"object","properties":{"foo":{"type":"integer"},"bar":{"type":"integer"}}}',
            );
            self::fail('Should have thrown a JsonSchemaValidationException');
        } catch (JsonSchemaValidationException $e) {
            self::assertSame(
                <<<'EOF'
                The properties must match schema: bar
                The data (string) must match the type: integer
                EOF
                ,
                $e->errorsAsMultilineString(),
            );
        }
    }

    /**
     * @dataProvider provideErrorsFormattedLikeMessageBag
     */
    public function testErrorsFormattedLikeMessageBag($data, string $schema, array $expectedErrors): void
    {
        try {
            SchemaValidator::validateOrThrow($data, $schema);
            self::fail('Should have thrown a JsonSchemaValidationException');
        } catch (JsonSchemaValidationException $e) {
            self::assertSame($expectedErrors, $e->errors());
        }
    }

    public function provideErrorsFormattedLikeMessageBag(): array
    {
        return [
            'simple' => [
                ['foo' => 1, 'bar' => 'two'],
                '{"type":"object","properties":{"foo":{"type":"integer"},"bar":{"type":"integer"}}}',
                ['bar' => ['The data (string) must match the type: integer']],
            ],
            'deep' => [
                ['foo' => ['bar' => 'two']],
                '{"type":"object","properties":{"foo":{"type":"object", "properties":{"bar":{"type":"integer"}}}}}',
                ['foo.bar' => ['The data (string) must match the type: integer']],
            ],
            'oneOf' => [
                null,
                '{"oneOf": [{"type": "string"},{"type": "number"}]}',
                [
                    'root element' => [
                        'The data (null) must match the type: string',
                        'The data (null) must match the type: number',
                    ],
                ],
            ],
        ];
    }
}
