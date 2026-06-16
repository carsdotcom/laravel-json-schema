<?php

/**
 * Given data and the name of a JSON Schema, validate the data
 * This is implemented as a Service because we generally want to use
 * the Singleton to speed up schema resolution in the loader
 */

namespace Carsdotcom\JsonSchemaValidation;

use Carsdotcom\JsonSchemaValidation\Exceptions\JsonSchemaValidationException;
use DomainException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Uri;
use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Validator;
use Symfony\Component\HttpFoundation\Response;

class SchemaValidatorService
{
    /** @var Validator */
    protected $validator = null;

    /**
     * As needed, build a validator in memory that knows how to load schema from local disk.
     * @return Validator
     */
    protected function getValidator(): Validator
    {
        if (!$this->validator) {
            if (empty(config('json-schema.base_url'))) {
                throw new DomainException(
                    'Laravel JSON Schema base_url is empty. This can be updated in /config/json-schema.php'
                );
            }

            $this->validator = new Validator();
            $this->validator->loader()->setBaseUri(Uri::parse(config('json-schema.base_url')));
            $this->validator->resolver()->registerPrefix(
                config('json-schema.base_url'),
                config('json-schema.local_base_prefix')
            );
            $this->validator->resolver()->registerPrefix(
                'https://unit.test/',
                config('json-schema.local_base_prefix_tests')
            );
        }
        return $this->validator;
    }

    /**
     * Given data (could be a JsonModel, associative-array style JSON, primitive)
     * and a schema (could be a URI, an object literal, a JSON-encoded string)
     * return whether the data validates against the schema, and hang on to any errors
     * @param mixed $data
     * @param mixed $schema
     * @param bool $normalizeWithJsonSerializable See {@see normalizeData()} for when to pass false.
     * @return bool
     */
    public function validate($data, $schema, bool $normalizeWithJsonSerializable = true): bool
    {
        $result = $this->validationResult($data, $schema, $normalizeWithJsonSerializable);
        return $result->isValid();
    }

    /**
     * Given data (could be a JsonModel, associative-array style JSON, primitive)
     * and a schema (could be a URI, an object literal, a JSON-encoded string)
     * return the ValidationResult of the upstream Opis library.
     * This is a good approach for methods that want to do their own error formatting through a deeper relationship with Opis
     * @param mixed $data
     * @param mixed $schema
     * @param bool $normalizeWithJsonSerializable See {@see normalizeData()} for when to pass false.
     * @return ValidationResult
     */
    public function validationResult($data, $schema, bool $normalizeWithJsonSerializable = true): ValidationResult
    {
        $validator = $this->getValidator();
        $data = $this->normalizeData($data, $normalizeWithJsonSerializable);
        return $validator->validate($data, $schema);
    }

    /**
     * opis/json-schema doesn't accept associative-array style JSON, only object-style,
     * so associative arrays must be converted to stdClass before validation.
     *
     * Default ($normalizeWithJsonSerializable = true): round-trip through json_encode/json_decode.
     * This honors the JsonSerializable contract — JsonModels, Collections, DateTime, etc. are
     * converted via their serialized form — so it is safe for any input.
     *
     * Opt-out ($normalizeWithJsonSerializable = false): convert with Opis' own Helper::toJSON().
     * It still produces a full object-style copy of the data, but builds it directly instead of
     * round-tripping through a JSON string, so peak memory holds one structured copy rather than
     * a structured copy PLUS the full encoded string — a meaningful saving on very large payloads.
     * Only safe when $data contains no JsonSerializable/Collections/DateTime, because Helper::toJSON()
     * reads public object properties and does NOT invoke jsonSerialize().
     *
     * @param mixed $data
     * @param bool $normalizeWithJsonSerializable
     * @return mixed
     */
    private function normalizeData($data, bool $normalizeWithJsonSerializable = true)
    {
        if (!$normalizeWithJsonSerializable) {
            // Despite the name, Helper::toJSON returns a PHP stdClass/array tree (not a JSON string):
            // the object-style structure Opis requires, built without an intermediate encoded string.
            return Helper::toJSON($data);
        }

        return json_decode(json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * This method will attempt to validate the provided data against the provided schema.  If the data is found to be
     * invalid, then a `JsonSchemaValidationException` exception is thrown with a default message of "Request body
     * contains invalid data!"  If `$appendValidationDescriptions` is set to true, then a detailed description of why
     * the JSON was invalid will be added to the exception message.
     *
     * @param mixed $data
     * @param mixed $schema
     * @param string|null $exceptionMessage
     * @param bool $appendValidationDescriptions
     * @param int $failureHttpStatusCode override what http status code to use on validation failure: default is 400
     * @param bool $normalizeWithJsonSerializable See {@see normalizeData()} for when to pass false.
     * @return bool
     */
    public function validateOrThrow(
        $data,
        $schema,
        ?string $exceptionMessage = null,
        bool $appendValidationDescriptions = false,
        int $failureHttpStatusCode = Response::HTTP_BAD_REQUEST,
        bool $normalizeWithJsonSerializable = true,
    ): bool {
        $results = $this->validationResult($data, $schema, $normalizeWithJsonSerializable);
        if ($results->isValid() === false) {
            $message = $exceptionMessage ?: 'Request body contains invalid data!';

            if ($appendValidationDescriptions) {
                $prepend = "\r\n* ";
                $message .= $prepend . implode($prepend, (new ErrorFormatter())->formatFlat($results->error()));
            }

            Log::debug(
                "Json Schema Validation Error",
                [
                    'error' => (new ErrorFormatter())->format($results->error(), true, null, null),
                    'data' => $data
                ]
            );

            throw new JsonSchemaValidationException($message, $results->error(), null, $failureHttpStatusCode);
        }

        return true;
    }

    /**
     * This method will attempt to validate the provided JSON-encoded string against the provided schema.
     */
    public function validateEncodedStringOrThrow(
        string $data,
        $schema,
        ?string $exceptionMessage = null,
        bool $appendValidationDescriptions = false,
        int $failureHttpStatusCode = Response::HTTP_BAD_REQUEST,
    ): bool
    {
        // Using the non-associative decode is both how Opis documents it
        // https://opis.io/json-schema/2.x/quick-start.html
        // and is known to avoid `{}` vs `[]` confusion
        $decodedData = json_decode($data, associative: false, flags: JSON_THROW_ON_ERROR);
        return $this->validateOrThrow($decodedData, $schema, $exceptionMessage, $appendValidationDescriptions, $failureHttpStatusCode);
    }

    /**
     * Given anything that Opis can use as a schema (object, boolean, json-encoded string)
     * register the schema into our namespace, so it can safely contain relative links of its own.
     * The returned string can be used as the second arg to ->validate.
     *      It looks like a URI, but it can't be used in documentation, you should treat it as a magic value.
     * @param bool|object|string $schema
     * @return string   Returns the absolute path that you can use as the second param to ->validate
     */
    public function registerRawSchema(bool|object|string $schema): string
    {
        $absoluteSchema = config('json-schema.base_url') . 'raw-' . hash('sha256', json_encode($schema));
        $this->getValidator()
            ->resolver()
            ->registerRaw($schema, $absoluteSchema);
        return $absoluteSchema;
    }

    /**
     * Load a local schema and return the decoded object style.
     * @param string $relativeUri
     * @param bool $associative
     * @return object|array
     */
    public static function getSchemaContents(string $relativeUri, bool $associative = true): array|object
    {
        $relativeUri = trim($relativeUri, '#');
        $schemaContent = Storage::disk(config('json-schema.storage_disk_name'))->get($relativeUri);
        if (is_null($schemaContent)) {
            throw new FileNotFoundException();
        }
        return json_decode($schemaContent, $associative, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Save a new schema to the appropriate location on disk.
     * Used by code that creates schemas, like artisan schemas:generate
     * @param string $relativeUri
     * @param array|object $schema
     */
    public static function putSchemaContents(string $relativeUri, array|object $schema): void
    {
        $relativeUri = trim($relativeUri, '#');
        Storage::disk(config('json-schema.storage_disk_name'))->put(
            $relativeUri,
            json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );
    }
}
