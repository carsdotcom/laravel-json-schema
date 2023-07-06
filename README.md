# laravel-json-schema
Use JsonSchema in Laravel apps

## Purpose

This library builds on the _outstanding_ JsonSchema validator [opis/json-schema](https://opis.io/json-schema/)

The entire intent of this library is to make JsonSchema feel like a first class citizen in a Laravel project.

It adds a new config file, `config/json-schema.php` to configure your root directory for self-hosted schema files.

It adds a Facade, `SchemaValidator` that can be used to instantiate the validator with appropriate loaders.

It adds new PhpUnit assertions in `JsonSchemaAssertions` like validating that a mixed item validates for a specific schema.

Most interestingly, it lets you use JsonSchema to validate incoming Requests bodies, and/or validate your own outgoing response bodies, all using JsonSchema schemas that you can then export into OpenAPI documentation.

## Coming Soon

More documentation will be coming soon, including some more projects that build on this, including Guzzle outgoing- and incoming-body validation, and a new kind of Laravel Model that persists to JSON instead of to a relational database.
