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

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the ConvertKit package.
 *
 * Bootstraps the ConvertKit package by registering services and bindings.
 * Extend this class with configuration, migrations, routes, views, and
 * other service registrations as the package grows.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class ConvertKitServiceProvider extends ServiceProvider
{
    /**
     * Registers any application services.
     *
     * Binds the ConvertKit class as a singleton in the container.
     * Add additional service registrations here.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton( 'convertkit', function ( $app ) {
            return new ConvertKit();
        } );
    }

    /**
     * Bootstraps any application services.
     *
     * Add package bootstrapping here such as:
     * - Configuration publishing: $this->publishes([...])
     * - Migration loading: $this->loadMigrationsFrom(...)
     * - View loading: $this->loadViewsFrom(...)
     * - Route loading: $this->loadRoutesFrom(...)
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function boot(): void
    {
        // Add package bootstrapping here
    }
}
