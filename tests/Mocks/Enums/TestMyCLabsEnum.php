<?php

namespace Tests\Mocks\Enums;

use Carsdotcom\JsonSchemaValidation\Traits\GeneratesSchemaMyCLabsTrait;
use MyCLabs\Enum\Enum;

class TestMyCLabsEnum extends Enum
{
    use GeneratesSchemaMyCLabsTrait;

    const SCHEMA = 'test/my-clabs-enum.json';
    const FOO = 'foo';
    const BAR = 'bar';
    const BAZ = 'baz';
}
