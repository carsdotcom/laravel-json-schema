<?php

namespace Tests\Mocks\Enums;

use Carsdotcom\JsonSchemaValidation\Traits\GeneratesSchemaTrait;

enum TestBackedEnumNoSchema: string
{
    use GeneratesSchemaTrait;

    case Foo = 'foo';
    case Bar = 'bar';
}
