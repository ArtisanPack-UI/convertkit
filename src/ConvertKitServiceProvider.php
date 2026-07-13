<?php

/**
 * ConvertKit service provider.
 *
 * Bootstraps the ConvertKit package by registering services and bindings.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit;

use ArtisanPackUI\ConvertKit\Api\Client;
use ArtisanPackUI\ConvertKit\Console\SyncCommand;
use ArtisanPackUI\ConvertKit\Console\TestCommand;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the ConvertKit package.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class ConvertKitServiceProvider extends ServiceProvider
{
    /**
     * Registers container bindings.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        $this->mergeConfigFrom( __DIR__ . '/../config/convertkit.php', 'convertkit' );

        $this->app->singleton( Client::class, function ( $app ): Client {
            /** @var ConfigRepository $config */
            $config = $app->make( 'config' );

            /** @var HttpFactory $http */
            $http = $app->make( HttpFactory::class );

            return new Client(
                http                 : $http,
                apiKey               : $config->get( 'convertkit.api_key' ),
                baseUrl              : (string) $config->get( 'convertkit.base_url', 'https://api.kit.com/v4' ),
                timeout              : (int) $config->get( 'convertkit.timeout', 15 ),
                retries              : (int) $config->get( 'convertkit.retries', 3 ),
                retryDelayMs         : (int) $config->get( 'convertkit.retry_delay', 500 ),
                maxBackoffMs         : (int) $config->get( 'convertkit.max_backoff', 30_000 ),
                maxRetryAfterSeconds : (int) $config->get( 'convertkit.max_retry_after', 60 ),
                allowInsecureHttp    : (bool) $config->get( 'convertkit.allow_insecure_http', false ),
            );
        } );

        $this->app->singleton( EndpointFactory::class, function ( $app ): EndpointFactory {
            return new EndpointFactory(
                $app->make( Client::class ),
                $app->make( CacheFactory::class ),
                $app->make( 'config' ),
            );
        } );

        $this->app->singleton( ConvertKit::class, function ( $app ): ConvertKit {
            return new ConvertKit( $app->make( EndpointFactory::class ) );
        } );

        $this->app->alias( ConvertKit::class, 'convertkit' );
    }

    /**
     * Bootstraps package services.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        if ( $this->app->runningInConsole() ) {
            $this->publishes( [
                __DIR__ . '/../config/convertkit.php' => config_path( 'convertkit.php' ),
            ], 'convertkit-config' );

            $this->commands( [
                TestCommand::class,
                SyncCommand::class,
            ] );
        }
    }
}
