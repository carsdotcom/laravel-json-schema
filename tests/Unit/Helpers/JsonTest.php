<?php
/**
 * Unit tests for JSON helpers.
 * Note the original class was imported from PHPUnit utilities, so these tests aren't exhaustive
 */

namespace Tests\Unit\Helpers;

use Carsdotcom\JsonSchemaValidation\Helpers\Json;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\BaseTestCase;
use Tests\Mocks\Models\Vehicle;

class JsonTest extends BaseTestCase
{
    public function testMugglifyPropertyExists()
    {
        $vehicle = Vehicle::factory()->make(['vin' => '11111111111111111']);
        self::assertFalse(property_exists($vehicle, 'vin'), 'property_exists fails for class object');
        $flatVehicle = Json::mugglify($vehicle, false);
        self::assertTrue(property_exists($flatVehicle, 'vin'), 'property_exists succeeds after flattening');
    }

    public function testCanonicalize()
    {
        $ab = '{ "a" : 1, "b" : 2 }';
        $ba = '{ "b" : 2, "a" : 1 }';

        self::assertNotSame($ab, $ba);
        self::assertSame('{"a":1,"b":2}', Json::canonicalize($ab), 'No change to order, normalizes whitespace');
        self::assertSame('{"a":1,"b":2}', Json::canonicalize($ba));
        self::assertSame(Json::canonicalize($ab), Json::canonicalize($ba));
    }

    /**
     * @param $thing1
     * @param $thing2
     * @param $expected
     * @throws \Exception
     * @dataProvider provideCanonicallySame
     */
    #[DataProvider('provideCanonicallySame')]
    public function testCanonicallySame($thing1, $thing2, bool $expected)
    {
        self::assertSame($expected, Json::canonicallySame($thing1, $thing2));
    }

    public static function provideCanonicallySame(): iterable
    {
        return [
            'simple strings match' => ['ab', 'ab', true],
            'simple strings miss' => ['ab', 'abc', false],
            'null and false not equivalent' => [null, false, false],
            'string and number not equivalent' => ['0', 0, false],
            'equal properties match' => [['a' => 1, 'b' => 2], ['a' => 1, 'b' => 2], true],
            'unequal properties miss' => [['a' => 2, 'b' => 2], ['a' => 1, 'b' => 2], false],
            'ignores property order, match' => [['a' => 1, 'b' => 2], ['b' => 2, 'a' => 1], true],
        ];
    }

    public function testDecodeOrThrowThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('json_decode error: Syntax error');
        Json::decodeOrThrow('{"incomplete":');
    }

    public function testDecodeOrThrowIsCoolWithNull(): void
    {
        // The way it notices decode errors isn't based on the return value of json_decode
        self::assertNull(Json::decodeOrThrow('null'));
    }

    /**
     * @dataProvider provideIsObject
     */
    #[DataProvider('provideIsObject')]
    public function testIsObject(mixed $subject, bool $expected): void
    {
        self::assertSame($expected, Json::isObject($subject));
    }

    public static function provideIsObject(): iterable
    {
        return [
            [null, false],
            [0, false],
            [1, false],
            [1.1, false],
            ['stringy', false],
            ['Object', false],
            [false, false],
            [[], false],
            [new Collection(), false],
            [(object) [], true],
            [['foo' => 'bar'], true],
            [(object) ['foo' => 'bar'], true],
        ];
    }
}
