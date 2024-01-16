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

This package supports Laravel `v9` and `v10`

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
        'root' => base_path('app/Schemas'), // must match the 'config.json-schema.local_base_prefix' value
    ]
]
```
3. Add your schema files to the `app/Schemas` folder. You may create subfolders to keep things organized.

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
