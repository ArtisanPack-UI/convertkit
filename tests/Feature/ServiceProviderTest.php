<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Route;

it( 'prepends the package throttle onto the subscribe route by default', function (): void {
    $route = Route::getRoutes()->getByName( 'convertkit.subscribers.store' );

    expect( $route )->not->toBeNull();

    $throttles = array_values( array_filter(
        $route->gatherMiddleware(),
        fn ( $m ): bool => is_string( $m ) && str_starts_with( $m, 'throttle:' ),
    ) );

    expect( $throttles )->toBe( [ 'throttle:10,1' ] );
} );

it( 'does not stack its throttle when the consumer supplied their own', function (): void {
    // Reboot the provider with a consumer-supplied throttle in the
    // configured middleware stack. The package must defer to it instead
    // of appending a second competing limiter.
    config()->set( 'convertkit.subscribe.middleware', [ 'throttle:60,1' ] );

    $provider = $this->app->getProvider( ArtisanPackUI\ConvertKit\ConvertKitServiceProvider::class );
    $provider->boot();

    // Re-fetch the route after reboot (the previous instance still holds
    // the old middleware — the new registration adds a duplicate route
    // with the new stack; we want the one that has the consumer throttle).
    $matching = array_values( array_filter(
        Route::getRoutes()->getRoutes(),
        fn ( $r ): bool => 'convertkit/subscribers' === $r->uri()
            && in_array( 'POST', $r->methods(), true )
            && in_array( 'throttle:60,1', $r->gatherMiddleware(), true ),
    ) );

    expect( $matching )->not->toBeEmpty();

    foreach ( $matching as $route ) {
        $throttles = array_values( array_filter(
            $route->gatherMiddleware(),
            fn ( $m ): bool => is_string( $m ) && str_starts_with( $m, 'throttle:' ),
        ) );

        expect( $throttles )->toBe( [ 'throttle:60,1' ] );
    }
} );
