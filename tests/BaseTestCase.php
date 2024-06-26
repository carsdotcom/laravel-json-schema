<?php

namespace Tests;

use Orchestra\Testbench\TestCase;

class BaseTestCase extends TestCase
{
    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('json-schema.base_url', 'http://localhost/');
        $app['config']->set('json-schema.local_base_prefix', dirname(__FILE__) . '/../tests/Schemas');
        $app['config']->set('json-schema.local_base_prefix_tests', dirname(__FILE__) . '/../tests/Schemas');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }
}