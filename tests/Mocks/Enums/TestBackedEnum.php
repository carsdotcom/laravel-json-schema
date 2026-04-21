<?php

namespace Tests\Mocks\Enums;

use Carsdotcom\JsonSchemaValidation\Traits\GeneratesSchemaTrait;

enum TestBackedEnum: string
{
    use GeneratesSchemaTrait;

    const SCHEMA = 'test/backed-enum.json';

    case Foo = 'foo';
    case Bar = 'bar';
    case Baz = 'baz';
}
