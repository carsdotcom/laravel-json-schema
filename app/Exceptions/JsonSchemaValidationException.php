<?php
/**
 * Exception thrown by failing JsonSchema validation.
 * Formats errors into the same structure you'd get from
 * Laravel ValidationException->validator->errors()
 */
declare(strict_types=1);

namespace Carsdotcom\JsonSchemaValidation\Exceptions;

use Carsdotcom\JsonSchemaValidation\Contracts\HasExtendedExceptionData;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class JsonSchemaValidationException
 * @package Carsdotcom\JsonSchemaValidation\Exceptions
 */
class JsonSchemaValidationException extends RuntimeException implements HasExtendedExceptionData
{
    public function __construct(
        string $message,
        protected ValidationError $error,
        \Throwable $previous = null,
        protected int $failureHttpStatusCode = Response::HTTP_BAD_REQUEST,
    ) {
        parent::__construct(message: $message, code: $failureHttpStatusCode, previous: $previous);
    }

    /**
     * @return array formatted like message bag
     */
    public function errors(): array
    {
        return (new ErrorFormatter())->format($this->error, true, null, [$this, 'formatErrorKey']);
    }

    public function errorsAsMultilineString(): string
    {
        return implode("\n", (new ErrorFormatter())->formatFlat($this->error));
    }

    /**
     * Returns extra properties that will be appended to this exception when rendered by app/Exceptions/Handler.php
     * @return array
     */
    public function getExtendedData(): array
    {
        return ['errors' => $this->errors()];
    }

    /**
     * We format our error keys using dot-notation, Opis by default would use Json-pointer
     * @param ValidationError $error
     * @return string
     */
    public static function formatErrorKey(ValidationError $error): string
    {
        $path = $error->data()->fullPath();
        if (!$path) {
            return 'root element';
        }
        return implode('.', $path);
    }

    /**
     * Used in Handler::determineMessageStringAndStatusCode() to determine return http status code
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->failureHttpStatusCode;
    }
}
