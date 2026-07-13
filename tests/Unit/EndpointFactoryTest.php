<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\ConvertKit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it( 'isolates cached reference data by API key so rotating keys cannot serve cross-account data', function (): void {
    Cache::store( 'array' )->flush();

    Http::fakeSequence()
        ->push( [ 'forms' => [ [ 'id' => 1, 'name' => 'Account A form' ] ] ], 200 )
        ->push( [ 'forms' => [ [ 'id' => 99, 'name' => 'Account B form' ] ] ], 200 );

    config()->set( 'convertkit.api_key', 'account-a-key' );
    app()->forgetInstance( ArtisanPackUI\ConvertKit\Api\Client::class );
    app()->forgetInstance( ArtisanPackUI\ConvertKit\EndpointFactory::class );
    app()->forgetInstance( ConvertKit::class );

    $formsA = app( ConvertKit::class )->forms()->list();
    expect( $formsA )->toHaveCount( 1 );
    expect( $formsA[0]->name )->toBe( 'Account A form' );

    // Rotate the API key. The rebuilt endpoint's cache key must not collide
    // with account A's cache entry — the second fake response must be used.
    config()->set( 'convertkit.api_key', 'account-b-key' );
    app()->forgetInstance( ArtisanPackUI\ConvertKit\Api\Client::class );
    app()->forgetInstance( ArtisanPackUI\ConvertKit\EndpointFactory::class );
    app()->forgetInstance( ConvertKit::class );

    $formsB = app( ConvertKit::class )->forms()->list();
    expect( $formsB )->toHaveCount( 1 );
    expect( $formsB[0]->name )->toBe( 'Account B form' );

    Http::assertSentCount( 2 );
} );
