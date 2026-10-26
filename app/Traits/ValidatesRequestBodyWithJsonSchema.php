<?php
/**
 * Use on a FormRequest to validate the JSON request body against a JsonSchema instead of Laravel rules.
 *
 * This trait looks for a constant SCHEMA on the request class that uses it, usually a JsonSchema URI.
 *
 * It does not replace validateResolved(), so Laravel's pipeline (prepareForValidation, authorize, failedAuthorization)
 * runs as normal and an unauthorized caller is rejected before the body is read. The schema check runs in
 * passedValidation(). Do not override passedValidation() in a class that uses this trait: the class method wins,
 * and the schema check is silently skipped. To run extra checks, decorate validateResolved() instead:
 *
 *      public function validateResolved(): void
 *      {
 *          parent::validateResolved();
 *          $this->validateSomethingElse();
 *      }
 *
 * validated() and safe() return the schema-checked body only. Route and query data are never included.
 * The schema decides which body keys survive: with additionalProperties allowed, validated() returns the whole body.
 */
declare(strict_types=1);

namespace Carsdotcom\JsonSchemaValidation\Traits;

use Carsdotcom\JsonSchemaValidation\Exceptions\JsonSchemaValidationException;
use Carsdotcom\JsonSchemaValidation\SchemaValidator;
use Carsdotcom\JsonSchemaValidation\Validation\SchemaCheckedBodyValidator;
use DomainException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Trait ValidatesRequestBodyWithJsonSchema
 * @package Carsdotcom\JsonSchemaValidation\Traits
 */
trait ValidatesRequestBodyWithJsonSchema
{
    /**
     * Laravel calls this after authorize() and the (empty) rules pass.
     * @return void
     * @throws DomainException                  on misconfiguration
     * @throws HttpException                    if the body is not recognizably JSON
     * @throws JsonSchemaValidationException    if the body violates the schema
     */
    protected function passedValidation(): void
    {
        if (!defined(static::class . '::SCHEMA')) {
            throw new DomainException(
                static::class . ' has not configured the SCHEMA to be checked by ValidatesRequestBodyWithJsonSchema',
            );
        }

        if (!$this->isJson()) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Request body must be JSON');
        }

        try {
            SchemaValidator::validateEncodedStringOrThrow(
                $this->getContent(),
                static::SCHEMA,
                'Request body contains invalid data!',
            );
        } catch (JsonSchemaValidationException $exception) {
            $this->logRejectedBody($exception);
            throw $exception;
        }

        // Hand the checked body to the validator where FormRequest::validated() and safe() look for it.
        $body = json_decode($this->getContent(), true);
        $this->setValidator(
            new SchemaCheckedBodyValidator($this->container->make('translator'), is_array($body) ? $body : []),
        );
    }

    /**
     * Called when the body violates the schema, before the exception is rethrown.
     * Does nothing by default, because a body can hold personal data. Override to log it:
     * the raw body is available from $this->getContent().
     * @param JsonSchemaValidationException $exception
     * @return void
     */
    protected function logRejectedBody(JsonSchemaValidationException $exception): void
    {
        //
    }
}
