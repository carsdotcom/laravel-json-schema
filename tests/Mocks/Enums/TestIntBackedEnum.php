<?php

namespace Tests\Mocks\Enums;

use Carsdotcom\JsonSchemaValidation\Traits\GeneratesSchemaTrait;

enum TestIntBackedEnum: int
{
    use GeneratesSchemaTrait;

    const SCHEMA = 'test/int-backed-enum.json';

    case One = 1;
    case Two = 2;
}
