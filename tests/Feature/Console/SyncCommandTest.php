<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach( function (): void {
    Cache::store( 'array' )->flush();
} );

it( 'refreshes a single resource when named', function (): void {
    Http::fake( [
        'api.kit.com/v4/forms' => Http::response( [
            'forms' => [ [ 'id' => 1, 'name' => 'Newsletter' ] ],
        ], 200 ),
    ] );

    $this->artisan( 'convertkit:sync forms' )
        ->expectsOutputToContain( 'Refreshed forms: 1 record(s).' )
        ->assertExitCode( 0 );

    Http::assertSentCount( 1 );
} );

it( 'refreshes every resource when called with no argument', function (): void {
    Http::fake( [
        'api.kit.com/v4/forms'         => Http::response( [ 'forms' => [ [ 'id' => 1, 'name' => 'A' ] ] ], 200 ),
        'api.kit.com/v4/tags'          => Http::response( [ 'tags' => [ [ 'id' => 1, 'name' => 't' ], [ 'id' => 2, 'name' => 'u' ] ] ], 200 ),
        'api.kit.com/v4/custom_fields' => Http::response( [ 'custom_fields' => [] ], 200 ),
    ] );

    $this->artisan( 'convertkit:sync' )
        ->expectsOutputToContain( 'Refreshed forms: 1 record(s).' )
        ->expectsOutputToContain( 'Refreshed tags: 2 record(s).' )
        ->expectsOutputToContain( 'Refreshed fields: 0 record(s).' )
        ->assertExitCode( 0 );
} );

it( 'rejects an unknown resource with INVALID exit code', function (): void {
    $this->artisan( 'convertkit:sync bogus' )
        ->expectsOutputToContain( "Unknown resource 'bogus'" )
        ->assertExitCode( 2 );
} );

it( 'reports failure with non-zero exit code when the API errors out', function (): void {
    Http::fake( [ '*' => Http::response( [ 'message' => 'nope' ], 500 ) ] );

    $this->artisan( 'convertkit:sync forms' )
        ->expectsOutputToContain( 'Failed to refresh forms' )
        ->assertExitCode( 1 );
} );
