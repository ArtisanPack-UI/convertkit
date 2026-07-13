<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Http;

it( 'succeeds when the API returns account info', function (): void {
    Http::fake( [
        'api.kit.com/v4/account' => Http::response( [
            'account' => [ 'name' => 'Acme', 'plan_type' => 'creator-pro' ],
        ], 200 ),
    ] );

    $this->artisan( 'convertkit:test' )
        ->expectsOutputToContain( 'Kit API is reachable.' )
        ->expectsOutputToContain( 'Acme' )
        ->expectsOutputToContain( 'creator-pro' )
        ->assertExitCode( 0 );
} );

it( 'reports a config error with INVALID exit code when no API key is set', function (): void {
    config()->set( 'convertkit.api_key', null );

    $this->artisan( 'convertkit:test' )
        ->expectsOutputToContain( 'not configured' )
        ->assertExitCode( 2 ); // Command::INVALID
} );

it( 'reports an auth failure with non-zero exit code on 401', function (): void {
    Http::fake( [ '*' => Http::response( [ 'message' => 'bad key' ], 401 ) ] );

    $this->artisan( 'convertkit:test' )
        ->expectsOutputToContain( 'Authentication failed' )
        ->assertExitCode( 1 );
} );

it( 'reports a network error with non-zero exit code on connection failure', function (): void {
    Http::fake( function (): void {
        throw new Illuminate\Http\Client\ConnectionException( 'dns' );
    } );

    $this->artisan( 'convertkit:test' )
        ->expectsOutputToContain( 'Could not reach the Kit API' )
        ->assertExitCode( 1 );
} );
