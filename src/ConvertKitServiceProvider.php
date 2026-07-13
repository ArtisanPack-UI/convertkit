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
use ArtisanPackUI\ConvertKit\Console\FeedsCommand;
use ArtisanPackUI\ConvertKit\Console\SyncCommand;
use ArtisanPackUI\ConvertKit\Console\TestCommand;
use ArtisanPackUI\ConvertKit\Http\Controllers\FeedController;
use ArtisanPackUI\ConvertKit\Http\Controllers\SubscribeController;
use ArtisanPackUI\ConvertKit\Listeners\HandleFormSubmittedForKit;
use ArtisanPackUI\ConvertKit\Models\KitFeed;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
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
        $this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );

        $this->registerFeedRoutes();
        $this->registerSubscribeRoutes();
        $this->registerFormsIntegration();

        if ( $this->app->runningInConsole() ) {
            $this->publishes( [
                __DIR__ . '/../config/convertkit.php' => config_path( 'convertkit.php' ),
            ], 'convertkit-config' );

            $this->publishes( [
                __DIR__ . '/../database/migrations' => database_path( 'migrations' ),
            ], 'convertkit-migrations' );

            $this->commands( [
                TestCommand::class,
                SyncCommand::class,
                FeedsCommand::class,
            ] );
        }
    }

    protected function registerFeedRoutes(): void
    {
        $config = $this->app->make( 'config' );

        $prefix     = (string) $config->get( 'convertkit.feed_admin.route_prefix', 'admin/convertkit' );
        $middleware = (array) $config->get( 'convertkit.feed_admin.middleware', [] );

        // SubstituteBindings must be in the stack for implicit route
        // model binding to resolve `{convertkitFeed}` into a `KitFeed`.
        // Prepend it so a consumer who overrides `middleware` (e.g. for
        // an API context without the `web` group) still gets binding.
        $middleware = array_values( array_unique( array_merge(
            [ \Illuminate\Routing\Middleware\SubstituteBindings::class ],
            $middleware,
        ) ) );

        // Route parameter is scoped to this package (`{convertkitFeed}`)
        // rather than the generic `{feed}` so we cannot collide with an
        // app or another package that also binds `feed`. Laravel's
        // implicit binding resolves it from the `KitFeed` type-hint on
        // the controller — no explicit `Route::bind` needed.
        Route::group( [
            'prefix'     => $prefix,
            'middleware' => $middleware,
        ], function ( Router $router ): void {
            $router->get( 'feeds', [ FeedController::class, 'index' ] )->name( 'convertkit.feeds.index' );
            $router->post( 'feeds', [ FeedController::class, 'store' ] )->name( 'convertkit.feeds.store' );
            $router->get( 'feeds/{convertkitFeed}', [ FeedController::class, 'show' ] )->name( 'convertkit.feeds.show' );
            $router->match( [ 'put', 'patch' ], 'feeds/{convertkitFeed}', [ FeedController::class, 'update' ] )->name( 'convertkit.feeds.update' );
            $router->delete( 'feeds/{convertkitFeed}', [ FeedController::class, 'destroy' ] )->name( 'convertkit.feeds.destroy' );
            $router->post( 'feeds/{convertkitFeed}/test', [ FeedController::class, 'test' ] )->name( 'convertkit.feeds.test' );
        } );
    }

    protected function registerSubscribeRoutes(): void
    {
        $config = $this->app->make( 'config' );

        $prefix       = (string) $config->get( 'convertkit.subscribe.route_prefix', 'convertkit' );
        $middleware   = (array) $config->get( 'convertkit.subscribe.middleware', [] );
        $maxAttempts  = (int) $config->get( 'convertkit.subscribe.throttle.max_attempts', 10 );
        $decayMinutes = (int) $config->get( 'convertkit.subscribe.throttle.decay_minutes', 1 );

        // Prepend Laravel's per-IP throttle so a public endpoint can't
        // be turned into a spam relay. Consumers can raise / lower the
        // window via config but cannot remove it — unless they've
        // already supplied their own `throttle:*` entry in
        // `subscribe.middleware`, in which case we defer to it rather
        // than stacking two competing limiters on one route.
        $hasOwnThrottle = false;

        foreach ( $middleware as $entry ) {
            if ( is_string( $entry ) && str_starts_with( $entry, 'throttle:' ) ) {
                $hasOwnThrottle = true;
                break;
            }
        }

        if ( ! $hasOwnThrottle ) {
            $middleware = array_values( array_merge(
                [ "throttle:{$maxAttempts},{$decayMinutes}" ],
                $middleware,
            ) );
        }

        Route::group( [
            'prefix'     => $prefix,
            'middleware' => $middleware,
        ], function ( Router $router ): void {
            $router->post( 'subscribers', SubscribeController::class )->name( 'convertkit.subscribers.store' );
        } );
    }

    protected function registerFormsIntegration(): void
    {
        $config = $this->app->make( 'config' );

        $eventClass = ltrim( (string) $config->get( 'convertkit.forms_integration.form_submitted_event' ), '\\' );

        if ( '' === $eventClass ) {
            return;
        }

        // Always subscribe — the listener no-ops when
        // `forms_integration.enabled` is false, so config toggles at
        // runtime without needing to rebuild the provider.
        Event::listen( $eventClass, [ HandleFormSubmittedForKit::class, 'handle' ] );
    }
}
