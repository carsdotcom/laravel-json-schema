<?php

namespace Carsdotcom\JsonSchemaValidation;

use Carsdotcom\JsonSchemaValidation\Console\Commands\Schemas\Generate;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class SchemaValidatorProvider extends ServiceProvider implements DeferrableProvider
{
    public function register()
    {
        $this->app->singleton(SchemaValidatorService::class, function () {
            return new SchemaValidatorService();
        });
    }

    /**
     * Get the services provided by the provider. (So Laravel can defer loading until one is requested)
     *
     * @return array
     */
    public function provides()
    {
        return [SchemaValidatorService::class];
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/json-schema.php' => config_path('json-schema.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Generate::class,
            ]);
        }
    }
}
