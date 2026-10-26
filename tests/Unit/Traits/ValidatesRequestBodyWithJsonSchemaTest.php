<?php
/**
 * Test the ValidatesRequestBodyWithJsonSchema trait, which validates a FormRequest's JSON body against a JsonSchema
 * from Laravel's passedValidation() hook, so the rest of the FormRequest pipeline keeps working.
 */
declare(strict_types=1);

namespace Tests\Unit\Traits;

use Carsdotcom\JsonSchemaValidation\Exceptions\JsonSchemaValidationException;
use Carsdotcom\JsonSchemaValidation\Traits\ValidatesRequestBodyWithJsonSchema;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\BaseTestCase;

/**
 * Class ValidatesRequestBodyWithJsonSchemaTest
 * @package Tests\Unit\Traits
 */
class ValidatesRequestBodyWithJsonSchemaTest extends BaseTestCase
{
    /**
     * Build a request that is ready for validateResolved(), the way Laravel's container would hand it to a controller.
     * @param FormRequest $request   Instance of the request class under test
     */
    private function resolve(
        FormRequest $request,
        string $body,
        string $contentType = 'application/json',
        string $uri = '/widgets',
    ): FormRequest {
        $resolved = $request::create($uri, 'POST', [], [], [], ['CONTENT_TYPE' => $contentType], $body);
        $resolved->setContainer($this->app);
        return $resolved;
    }

    private function widgetRequest(): FormRequest
    {
        return new class extends FormRequest {
            use ValidatesRequestBodyWithJsonSchema;
            public const SCHEMA = 'widget.json';
        };
    }

    public function testValidBodyPasses(): void
    {
        $request = $this->resolve($this->widgetRequest(), '{"name":"sprocket","count":3}');
        $request->validateResolved();
        self::assertSame(['name' => 'sprocket', 'count' => 3], $request->validated());
    }

    public function testSchemaViolationThrowsJsonSchemaValidationException(): void
    {
        $request = $this->resolve($this->widgetRequest(), '{"name":"sprocket","count":"three"}');
        try {
            $request->validateResolved();
            self::fail('Expected a JsonSchemaValidationException');
        } catch (JsonSchemaValidationException $exception) {
            self::assertSame('Request body contains invalid data!', $exception->getMessage());
            self::assertSame(400, $exception->getStatusCode());
            self::assertArrayHasKey('count', $exception->errors());
        }
    }

    public function testMissingRequiredPropertyThrows(): void
    {
        $request = $this->resolve($this->widgetRequest(), '{"count":3}');
        $this->expectException(JsonSchemaValidationException::class);
        $request->validateResolved();
    }

    public function testNonJsonBodyThrows400(): void
    {
        $request = $this->resolve($this->widgetRequest(), 'name=sprocket', 'application/x-www-form-urlencoded');
        try {
            $request->validateResolved();
            self::fail('Expected an HttpException');
        } catch (HttpException $exception) {
            self::assertSame(400, $exception->getStatusCode());
            self::assertSame('Request body must be JSON', $exception->getMessage());
        }
    }

    public function testMissingSchemaConstantIsAMisconfiguration(): void
    {
        $request = new class extends FormRequest {
            use ValidatesRequestBodyWithJsonSchema;
        };
        $request = $this->resolve($request, '{"name":"sprocket"}');
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('has not configured the SCHEMA');
        $request->validateResolved();
    }

    public function testUnauthorizedRequestIsRejectedBeforeTheBodyIsRead(): void
    {
        $request = new class extends FormRequest {
            use ValidatesRequestBodyWithJsonSchema;
            public const SCHEMA = 'widget.json';

            public function authorize(): bool
            {
                return false;
            }
        };
        // This body is not JSON and not valid, but authorization must fail first
        $request = $this->resolve($request, 'garbage', 'text/plain');
        $this->expectException(AuthorizationException::class);
        $request->validateResolved();
    }

    public function testPrepareForValidationStillRuns(): void
    {
        $request = new class extends FormRequest {
            use ValidatesRequestBodyWithJsonSchema;
            public const SCHEMA = 'widget.json';
            public bool $prepared = false;

            protected function prepareForValidation(): void
            {
                $this->prepared = true;
            }
        };
        $request = $this->resolve($request, '{"name":"sprocket"}');
        $request->validateResolved();
        self::assertTrue($request->prepared);
    }

    public function testValidatedSupportsKeyAndDefault(): void
    {
        $request = $this->resolve($this->widgetRequest(), '{"name":"sprocket"}');
        $request->validateResolved();
        self::assertSame('sprocket', $request->validated('name'));
        self::assertSame(7, $request->validated('count', 7));
        self::assertNull($request->validated('count'));
    }

    public function testValidatedReturnsOnlyTheBodyNotQueryOrRouteData(): void
    {
        $request = $this->resolve($this->widgetRequest(), '{"name":"sprocket"}', uri: '/widgets?env=prod');
        $request->validateResolved();
        self::assertSame(['name' => 'sprocket'], $request->validated());
        self::assertNull($request->validated('env'));
    }

    public function testSafeReturnsTheBody(): void
    {
        $request = $this->resolve($this->widgetRequest(), '{"name":"sprocket","count":3}');
        $request->validateResolved();
        self::assertSame(['name' => 'sprocket', 'count' => 3], $request->safe()->all());
        self::assertSame(['name' => 'sprocket'], $request->safe(['name']));
    }

    public function testNestedBodyIsReturnedWhole(): void
    {
        $request = $this->resolve($this->widgetRequest(), '{"name":"sprocket","parts":{"gears":[1,2],"label":"x"}}');
        $request->validateResolved();
        self::assertSame(['gears' => [1, 2], 'label' => 'x'], $request->validated('parts'));
    }

    public function testTopLevelKeyContainingADot(): void
    {
        $request = $this->resolve($this->widgetRequest(), '{"name":"sprocket","a.b":"dotted"}');
        $request->validateResolved();
        self::assertSame(['name' => 'sprocket', 'a.b' => 'dotted'], $request->validated());
    }

    public function testListBodyIsReturned(): void
    {
        $request = $this->resolve($this->widgetRequest(), '[1,2,3]');
        $request->validateResolved();
        self::assertSame([1, 2, 3], $request->validated());
    }

    public function testScalarBodyThatSatisfiesNoSchemaThrows(): void
    {
        $request = $this->resolve($this->widgetRequest(), '3');
        $this->expectException(JsonSchemaValidationException::class);
        $request->validateResolved();
    }

    /**
     * The schema check must see the raw string, so an empty object stays an object ({} vs [] is not ambiguous)
     */
    public function testEmptyObjectBodySatisfiesTypeObject(): void
    {
        $request = new class extends FormRequest {
            use ValidatesRequestBodyWithJsonSchema;
            public const SCHEMA = '{"type":"object"}';
        };
        $request = $this->resolve($request, '{}');
        $request->validateResolved();
        self::assertSame([], $request->validated());
    }

    public function testEmptyArrayBodyDoesNotSatisfyTypeObject(): void
    {
        $request = new class extends FormRequest {
            use ValidatesRequestBodyWithJsonSchema;
            public const SCHEMA = '{"type":"object"}';
        };
        $request = $this->resolve($request, '[]');
        try {
            $request->validateResolved();
            self::fail('Expected a JsonSchemaValidationException');
        } catch (JsonSchemaValidationException $exception) {
            self::assertSame(
                ['root element' => ['The data (array) must match the type: object']],
                $exception->errors(),
            );
        }
    }

    public function testScalarBodyReportsRootElementError(): void
    {
        $request = new class extends FormRequest {
            use ValidatesRequestBodyWithJsonSchema;
            public const SCHEMA = '{"type":"object"}';
        };
        $request = $this->resolve($request, '"Stringy string string"');
        try {
            $request->validateResolved();
            self::fail('Expected a JsonSchemaValidationException');
        } catch (JsonSchemaValidationException $exception) {
            self::assertSame(
                ['root element' => ['The data (string) must match the type: object']],
                $exception->errors(),
            );
            self::assertSame(400, $exception->getStatusCode());
        }
    }

    public function testUnparseableJsonBodyThrows400(): void
    {
        $request = $this->resolve($this->widgetRequest(), '{"name":');
        try {
            $request->validateResolved();
            self::fail('Expected a 400 HttpException');
        } catch (HttpException $exception) {
            self::assertSame(400, $exception->getStatusCode());
        }
    }

    public function testEmptyBodyWithJsonContentTypeThrows400(): void
    {
        $request = $this->resolve($this->widgetRequest(), '');
        try {
            $request->validateResolved();
            self::fail('Expected a 400 HttpException');
        } catch (HttpException $exception) {
            self::assertSame(400, $exception->getStatusCode());
        }
    }

    public function testEmptyObjectBodyDoesNotSatisfyTypeArray(): void
    {
        $request = new class extends FormRequest {
            use ValidatesRequestBodyWithJsonSchema;
            public const SCHEMA = '{"type":"array"}';
        };
        $request = $this->resolve($request, '{}');
        $this->expectException(JsonSchemaValidationException::class);
        $request->validateResolved();
    }

    public function testLogRejectedBodyIsCalledOnSchemaFailure(): void
    {
        $request = new class extends FormRequest {
            use ValidatesRequestBodyWithJsonSchema;
            public const SCHEMA = 'widget.json';
            public ?JsonSchemaValidationException $logged = null;
            public ?string $loggedBody = null;

            protected function logRejectedBody(JsonSchemaValidationException $exception): void
            {
                $this->logged = $exception;
                $this->loggedBody = $this->getContent();
            }
        };
        $request = $this->resolve($request, '{"count":3}');
        try {
            $request->validateResolved();
            self::fail('Expected a JsonSchemaValidationException');
        } catch (JsonSchemaValidationException $exception) {
            self::assertSame($exception, $request->logged);
            self::assertSame('{"count":3}', $request->loggedBody);
        }
    }

    public function testLogRejectedBodyIsNotCalledForNonJsonBody(): void
    {
        $request = new class extends FormRequest {
            use ValidatesRequestBodyWithJsonSchema;
            public const SCHEMA = 'widget.json';
            public bool $logged = false;

            protected function logRejectedBody(JsonSchemaValidationException $exception): void
            {
                $this->logged = true;
            }
        };
        $request = $this->resolve($request, 'name=sprocket', 'application/x-www-form-urlencoded');
        try {
            $request->validateResolved();
            self::fail('Expected an HttpException');
        } catch (HttpException) {
            self::assertFalse($request->logged);
        }
    }

    public function testDecoratingValidateResolvedStillRunsTheSchemaCheck(): void
    {
        $request = new class extends FormRequest {
            use ValidatesRequestBodyWithJsonSchema;
            public const SCHEMA = 'widget.json';
            public bool $extraCheckRan = false;

            public function validateResolved(): void
            {
                parent::validateResolved();
                $this->extraCheckRan = true;
            }
        };

        $valid = $this->resolve(clone $request, '{"name":"sprocket"}');
        $valid->validateResolved();
        self::assertTrue($valid->extraCheckRan);

        $invalid = $this->resolve(clone $request, '{"count":3}');
        try {
            $invalid->validateResolved();
            self::fail('Expected a JsonSchemaValidationException');
        } catch (JsonSchemaValidationException) {
            self::assertFalse($invalid->extraCheckRan);
        }
    }
}
