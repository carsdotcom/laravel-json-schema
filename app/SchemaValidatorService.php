<?php

/**
 * Given data and the name of a JSON Schema, validate the data
 * This is implemented as a Service because we generally want to use
 * the Singleton to speed up schema resolution in the loader
 */

namespace Carsdotcom\JsonSchemaValidation;

use Carsdotcom\JsonSchemaValidation\Exceptions\JsonSchemaValidationException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Uri;
use Opis\JsonSchema\Validator;
use Symfony\Component\HttpFoundation\Response;

class SchemaValidatorService
{
    /** @var Validator */
    protected $validator = null;

    /**
     * error from the most recent ->validate() call
     * @var ValidationError
     */
    protected $error = null;

    /**
     * As needed, build a validator in memory that knows how to load schema from local disk.
     * @return Validator
     */
    protected function getValidator(): Validator
    {
        if (!$this->validator) {
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
     * @return bool
     */
    public function validate($data, $schema): bool
    {
        $validator = $this->getValidator();
        $data = $this->normalizeData($data);
        $result = $validator->validate($data, $schema);
        $this->error = $result->error();
        return $result->isValid();
    }

    /**
     * opis/json-schema doesn't accept associative-array style JSON, only object-style.
     * Since Laravel and a lot of our code uses associative-arrays, this converts it.
     * This also uses the object's JsonSerializable contract to get the exportable version,
     * and even converts Collections to flat arrays
     * @param mixed $data
     * @return mixed
     */
    private function normalizeData($data)
    {
        return json_decode(json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * Return an array of errors from the last *Validation call
     * @return ValidationError|null
     */
    public function getError(): ?ValidationError
    {
        return $this->error;
    }

    /**
     * Return an array of human readable errors from the last *Validation call
     * @return array
     */
    public function getFormattedError(): array
    {
        if (!$this->error) {
            return [];
        }

        return (new ErrorFormatter())->formatFlat($this->error);
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
     * @return bool
     */
    public function validateOrThrow(
        $data,
        $schema,
        string $exceptionMessage = null,
        bool $appendValidationDescriptions = false,
        int $failureHttpStatusCode = Response::HTTP_BAD_REQUEST,
    ): bool {
        if ($this->validate($data, $schema) === false) {
            $message = $exceptionMessage ?: 'Request body contains invalid data!';

            if ($appendValidationDescriptions) {
                $prepend = "\r\n* ";
                $message .= $prepend . implode($prepend, $this->getFormattedError());
            }

            Log::debug(
                "Json Schema Validation Error",
                [
                    'error' => (new ErrorFormatter())->format($this->error, true, null, null),
                    'data' => $data
                ]
            );

            throw new JsonSchemaValidationException($message, $this->getError(), null, $failureHttpStatusCode);
        }

        return true;
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
