<?php
/**
 * Imported from PHPUnit\Util\Json
 *
 * We need these methods to cheaply do deep-object comparison,
 * (e.g. in HasJsonModelAttributes::isJsonModelAttributeDirty)
 * but we don't install PHPUnit in production.
 */

namespace Carsdotcom\JsonSchemaValidation\Helpers;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use function count;
use function is_array;
use function is_object;
use function json_decode;
use function json_encode;
use function json_last_error;
use function ksort;

/**
 * Class Json
 * @package App\Helpers
 */
class Json
{
    /**
     * Given a complex object, especially one with "magic" properties that would fail property_exists,
     * remove all the magic. (Like a Muggle https://en.wikipedia.org/wiki/Muggle)
     * turn it into a simple array or standard-object representation.
     * Uses JSON so arbitrary depth all gets mugglified at once.
     * @param $stuff
     * @param bool $associativeStyle   Like second arg to json_decode
     * @return mixed
     */
    public static function mugglify($stuff, $associativeStyle = true)
    {
        return json_decode(json_encode($stuff), $associativeStyle);
    }

    /**
     * To allow comparison of JSON strings, first process them into a consistent
     * format so that they can be compared as strings.
     * @param string $json
     * @param int $options
     * @return string
     */
    public static function canonicalize(string $json, int $options = 0): string
    {
        $decodedJson = self::decodeOrThrow($json);

        self::recursiveSort($decodedJson);

        $reencodedJson = json_encode($decodedJson, $options);

        return $reencodedJson;
    }

    /**
     * Compare any two JSON serializable things, where order of string object properties is ignored.
     * Returns true if they are, canonically, identical
     * @param $thing1
     * @param $thing2
     * @return bool
     * @throws \Exception
     */
    public static function canonicallySame($thing1, $thing2): bool
    {
        return self::canonicalize(json_encode($thing1)) === self::canonicalize(json_encode($thing2));
    }

    /**
     * Compare any two JSON serializable things, where order of string object properties is ignored.
     * Creates an internal muggle representation without the dotted-notation keys in $ignoreKeys
     * Returns true if those muggles are, canonically, identical
     * @param $thing1
     * @param $thing2
     * @param array $ignoreKeys
     * @return bool
     * @throws \Exception
     */
    public static function canonicallySameExcept($thing1, $thing2, array $ignoreKeys): bool
    {
        $muggle1 = self::mugglify($thing1);
        Arr::forget($muggle1, $ignoreKeys);
        $muggle2 = self::mugglify($thing2);
        Arr::forget($muggle2, $ignoreKeys);
        return self::canonicallySame($muggle1, $muggle2);
    }

    /*
     * JSON object keys are unordered while PHP array keys are ordered.
     * Sort all array keys to ensure both the expected and actual values have
     * their keys in the same order.
     */
    private static function recursiveSort(&$json): void
    {
        if (is_array($json) === false) {
            // If the object is not empty, change it to an associative array
            // so we can sort the keys (and we will still re-encode it
            // correctly, since PHP encodes associative arrays as JSON objects.)
            // But EMPTY objects MUST remain empty objects. (Otherwise we will
            // re-encode it as a JSON array rather than a JSON object.)
            // See #2919.
            if (is_object($json) && count((array) $json) > 0) {
                $json = (array) $json;
            } else {
                return;
            }
        }

        ksort($json);

        foreach ($json as $key => &$value) {
            self::recursiveSort($value);
        }
    }

    /**
     * Wrapper for json_decode that throws when an error occurs.
     * Cloned here from Guzzle, to make it obvious that it behaves differently from the builtin language function.
     *
     * @param string $json    JSON data to parse
     * @param bool $assoc     When true, returned objects will be converted
     *                        into associative arrays.
     * @param int    $depth   User specified recursion depth.
     * @param int    $options Bitmask of JSON decode options.
     *
     * @return mixed
     * @throws InvalidArgumentException if the JSON cannot be decoded.
     * @link http://www.php.net/manual/en/function.json-decode.php
     */
    public static function decodeOrThrow($json, $assoc = false, $depth = 512, $options = 0)
    {
        $data = json_decode($json, $assoc, $depth, $options);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new InvalidArgumentException('json_decode error: ' . json_last_error_msg());
        }

        return $data;
    }

    public static function isObject($mixed): bool
    {
        return Str::startsWith(json_encode($mixed, flags: JSON_THROW_ON_ERROR), '{');
    }
}
