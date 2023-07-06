<?php

namespace Carsdotcom\JsonSchemaValidation\Traits;

use Carsdotcom\JsonSchemaValidation\Contracts\CanValidate;
use Carsdotcom\JsonSchemaValidation\Helpers\FriendlyClassName;
use Carsdotcom\JsonSchemaValidation\SchemaValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

trait ValidatesResponsesTrait
{
    /**
     * Helper to return a JSON response.
     *
     * @param $value
     * @param int $status
     * @return JsonResponse
     */
    protected function json($value, int $status = 200): JsonResponse
    {
        return response()->json($value, $status);
    }

    /**
     * Helper to return a JSON response that conforms to one of:
     *    a passed schema,
     *    its own ->validate() call
     * @return JsonResponse
     * @throws \Exception depending on implementation of CanValidate
     */
    public function validatedJson($value, $schema = null, int $successHttpStatusCode = Response::HTTP_OK): JsonResponse
    {
        $message = 'Response failed validation.';
        $failureHttpStatusCode = Response::HTTP_INTERNAL_SERVER_ERROR; // Our output is malformed, blame ourselves, 500

        //Given an override schema, use that ignoring the $value's native validation or lack thereof
        if ($schema) {
            SchemaValidator::validateOrThrow($value, $schema, $message, failureHttpStatusCode: $failureHttpStatusCode);
            return $this->json($value, $successHttpStatusCode);
        }

        if ($value instanceof CanValidate) {
            $value->validateOrThrow($message, $failureHttpStatusCode);
            return $this->json($value, $successHttpStatusCode);
        }

        throw new \DomainException(
            'Response could not be validated, ' .
            (new FriendlyClassName())($value) .
            'does not implement the CanValidate contract.',
        );
    }
}