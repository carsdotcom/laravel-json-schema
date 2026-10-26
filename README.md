# laravel-json-schema
Use JsonSchema in Laravel apps

## Purpose

This library builds on the _outstanding_ JsonSchema validator [opis/json-schema](https://opis.io/json-schema/)

The entire intent of this library is to make JsonSchema feel like a first class citizen in a Laravel project.

- Adds a new config file, `config/json-schema.php`, to configure your root directory for self-hosted schema files.
- Adds the `SchemaValidator` facade that can be used to instantiate the validator with appropriate loaders.
- Adds new PhpUnit assertions in the `Carsdotcom\JsonSchemaValidation\Traits\JsonSchemaAssertions` trait, such as validating that a mixed item validates for a specific schema.
- Most interestingly, it lets you use JsonSchema to validate incoming Requests bodies, and/or validate your own outgoing response bodies, all using JsonSchema schemas that you can then export into OpenAPI documentation.

## Laravel Version Compatibility

This package supports Laravel `v10+` and PHP `8.1+`.

## Installation

```
composer require carsdotcom/laravel-json-schema
```

## Using Laravel JSON Schema

### Setup

#### Config File
Copy the `json-schema.php` file from the `vendor/carsdotcom/laravel-json-schema/config` folder to your application's `config` folder.

#### Schema Storage
1. Create a `Schemas` folder under your application root folder, such as `app/Schemas`.
2. Create a new storage disk under the `disks` key within your application's `config/filesystem.php` file:

```
'disks' => [
    'schemas' => [
        'driver' => 'local',
        'root' => app_path('app/Schemas'), // must match the 'config.json-schema.local_base_prefix' value
    ]
]
```
3. Add your schema files to the `app/Schemas` folder. You may create subfolders to keep things organized.

#### Generate Enum Schemas

_This is an optional step, but can be super helpful._

For **native PHP backed enums** (`BackedEnum`) — recommended for all new code:

1. Add `use Carsdotcom\JsonSchemaValidation\Traits\GeneratesSchemaTrait;` to the enum.
2. Add a `SCHEMA` constant whose value is the relative path to your schema file: `const SCHEMA = 'Acme/Enums/item_type.json';`
3. Run `php artisan schemas:generate`.

For **[MyCLabs enums](https://github.com/myclabs/php-enum)** (`MyCLabs\Enum\Enum` subclasses) — legacy support only, not recommended for new code:

1. Add `use Carsdotcom\JsonSchemaValidation\Traits\GeneratesSchemaMyCLabsTrait;` to the class.
2. Add a `SCHEMA` constant as above.
3. Run `php artisan schemas:generate`.

## Validating JSON Data Against a Schema

For this example, we'll be using these objects:

### Hosted JSON Schema File

This is assumed to be stored in your `app/Schemas` folder as `Product.json`.

```
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "https://example.com/product.schema.json",
  "title": "Product",
  "description": "A product from Acme's catalog",
  "type": "object",
  "properties": {
    "productId": {
      "description": "The unique identifier for a product",
      "type": "integer"
    },
    "productName": {
      "description": "Name of the product",
      "type": "string"
    },
    "price": {
      "description": "The price of the product",
      "type": "number",
      "exclusiveMinimum": 0
    }
  },
  "required": [ "productId", "productName", "price" ]
}
```

### JSON Data to be Validated

```
{
  "productId": 1,
  "productName": "An ice sculpture",
  "price": 12.50
}
```

### Application Code for Validation

```
use Carsdotcom\JsonSchemaValidation\SchemaValidator;

SchemaValidator::validateOrThrow($json, 'Product.json');
```

## Validating a Request Body in a FormRequest

Use `ValidatesRequestBodyWithJsonSchema` on a `FormRequest` to validate the JSON request body against a schema.
The trait keeps Laravel's normal validation pipeline and does not touch authorization.
`prepareForValidation()` and `authorize()` run as usual, and the schema check runs last.

```php
use Carsdotcom\JsonSchemaValidation\Traits\ValidatesRequestBodyWithJsonSchema;
use Illuminate\Foundation\Http\FormRequest;

class CreateProductRequest extends FormRequest
{
    use ValidatesRequestBodyWithJsonSchema;

    public const SCHEMA = 'Product.json';
}
```

- A body that is not JSON, or cannot be parsed as JSON (including an empty body), throws a 400 `HttpException`.
- The schema check reads the raw body string, so `{}` is an object and `[]` is an array.
- A body that violates the schema throws `JsonSchemaValidationException`, the same as `SchemaValidator::validateOrThrow()`.
- A request class without a `SCHEMA` constant throws a `DomainException`.
- Do not define `rules()`. The trait does not use Laravel rules.

### What `validated()` and `safe()` return

They return the schema-checked JSON body only. Route parameters and query parameters are never included.
Read them with `$request->route('id')` and `$request->query('page')`.
The schema decides which body keys remain. If the schema allows additional properties, `validated()` returns the whole body.

### Logging a rejected body

The trait does not log by default, because a body can hold personal data.
To log, override `logRejectedBody()`. It runs only when the body violates the schema.

```php
protected function logRejectedBody(JsonSchemaValidationException $exception): void
{
    Log::error('Invalid request body', [
        'url' => $this->url(),
        'body' => $this->getContent(),
        'errors' => $exception->errors(),
    ]);
}
```

### Adding your own checks

Do not override `passedValidation()`. A method on your class replaces the trait's method, and the schema check is skipped.
Decorate `validateResolved()` instead.

```php
public function validateResolved(): void
{
    parent::validateResolved();
    $this->validateRouteParameters();
}
```

## Additional Functionality

### Getting the Content of a Schema File

```
use Carsdotcom\JsonSchemaValidation\SchemaValidator;

SchemaValidator::getSchemaContents('Product.json');
```

### Storing a Schema File at a Specific Location

```
use Carsdotcom\JsonSchemaValidation\SchemaValidator;

SchemaValidator::getSchemaContents('Customer.json', $jsonSchemaForCustomer);
```

### Adding an In-Memory Schema File

```
$schemaKey = (new SchemaValidatorService)->registerRawSchema($jsonSchema);
```
