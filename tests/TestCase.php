<?php

declare( strict_types=1 );

namespace Tests;

use ArtisanPackUI\ConvertKit\ConvertKitServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * Base Test Case
 *
 * Provides base functionality for all ConvertKit package tests.
 *
 * @since   1.0.0
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Gets package providers.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Foundation\Application  $app  The application instance.
     *
     * @return array<int, class-string> Array of service provider class names.
     */
    protected function getPackageProviders( $app ): array
    {
        return [
            ConvertKitServiceProvider::class,
        ];
    }

    /**
     * Defines environment setup.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Foundation\Application  $app  The application instance.
     */
    protected function defineEnvironment( $app ): void
    {
        // Setup app key for encryption
        $app['config']->set( 'app.key', 'base64:' . base64_encode( random_bytes( 32 ) ) );

        // Setup default database to use sqlite :memory:
        $app['config']->set( 'database.default', 'testbench' );
        $app['config']->set( 'database.connections.testbench', [
            'driver'                  => 'sqlite',
            'database'                => ':memory:',
            'prefix'                  => '',
            'foreign_key_constraints' => true,
        ] );

        // ConvertKit test defaults. Retries=0 so tests don't burn wall time on
        // retryable statuses unless a specific test opts in.
        $app['config']->set( 'convertkit.api_key', 'test-key' );
        $app['config']->set( 'convertkit.base_url', 'https://api.kit.com/v4' );
        $app['config']->set( 'convertkit.retries', 0 );
        $app['config']->set( 'convertkit.retry_delay', 0 );
        $app['config']->set( 'convertkit.max_backoff', 30_000 );
        $app['config']->set( 'convertkit.max_retry_after', 60 );
        $app['config']->set( 'convertkit.allow_insecure_http', false );
        $app['config']->set( 'convertkit.cache.store', 'array' );
        $app['config']->set( 'convertkit.cache.forms_ttl', 3600 );
        $app['config']->set( 'convertkit.cache.tags_ttl', 3600 );
        $app['config']->set( 'convertkit.cache.fields_ttl', 3600 );
    }
}
