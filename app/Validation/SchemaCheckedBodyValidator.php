<?php
/**
 * A Laravel validator that holds a request body that has already passed JsonSchema validation.
 * FormRequest::validated() and safe() read from the request's validator, so giving them this one
 * returns the whole body without Laravel rules (which would drop top-level keys containing a dot).
 */
declare(strict_types=1);

namespace Carsdotcom\JsonSchemaValidation\Validation;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Validation\Validator;

/**
 * Class SchemaCheckedBodyValidator
 * @package Carsdotcom\JsonSchemaValidation\Validation
 */
class SchemaCheckedBodyValidator extends Validator
{
    /**
     * @param Translator $translator
     * @param array<array-key, mixed> $body   Decoded JSON body, already checked against its schema
     */
    public function __construct(
        Translator $translator,
        protected array $body,
    ) {
        parent::__construct($translator, $body, []);
    }

    /**
     * @return array<array-key, mixed> the schema-checked body
     */
    public function validated(): array
    {
        return $this->body;
    }
}
