<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Api\Client;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitAuthException;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitException;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitNotFoundException;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitRateLimitException;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitServerException;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitValidationException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it( 'sends the API key header and decodes JSON responses', function (): void {
    Http::fake( [
        'api.kit.com/v4/account' => Http::response( [ 'account' => [ 'name' => 'Acme' ] ], 200 ),
    ] );

    $data = app( Client::class )->get( 'account' );

    expect( $data )->toBe( [ 'account' => [ 'name' => 'Acme' ] ] );

    Http::assertSent( function ( Request $request ): bool {
        return 'GET' === $request->method()
            && 'https://api.kit.com/v4/account' === $request->url()
            && 'test-key' === $request->header( 'X-Kit-Api-Key' )[0];
    } );
} );

it( 'throws KitAuthException when the API key is missing', function (): void {
    config()->set( 'convertkit.api_key', null );

    app( Client::class )->get( 'account' );
} )->throws( KitAuthException::class, 'not configured' );

it( 'maps 401 responses to KitAuthException', function (): void {
    Http::fake( [ '*' => Http::response( [ 'message' => 'Bad key' ], 401 ) ] );

    app( Client::class )->get( 'account' );
} )->throws( KitAuthException::class, 'Bad key' );

it( 'maps 404 responses to KitNotFoundException', function (): void {
    Http::fake( [ '*' => Http::response( [ 'message' => 'Not found' ], 404 ) ] );

    app( Client::class )->get( 'subscribers/1' );
} )->throws( KitNotFoundException::class );

it( 'maps 422 responses to KitValidationException carrying errors', function (): void {
    Http::fake( [
        '*' => Http::response( [
            'message' => 'Validation failed',
            'errors'  => [ 'email_address' => [ 'is invalid' ] ],
        ], 422 ),
    ] );

    try {
        app( Client::class )->post( 'subscribers', [ 'email_address' => 'nope' ] );
        expect( false )->toBeTrue( 'expected KitValidationException to be thrown' );
    } catch ( KitValidationException $e ) {
        expect( $e->errors )->toBe( [ 'is invalid' ] );
        expect( $e->getStatusCode() )->toBe( 422 );
    }
} );

it( 'maps 500 responses to KitServerException', function (): void {
    Http::fake( [ '*' => Http::response( 'boom', 500 ) ] );

    app( Client::class )->get( 'account' );
} )->throws( KitServerException::class );

it( 'retries on 429 and eventually succeeds', function (): void {
    config()->set( 'convertkit.retries', 2 );

    Http::fakeSequence()
        ->push( [ 'message' => 'slow down' ], 429, [ 'Retry-After' => '0' ] )
        ->push( [ 'ok' => true ], 200 );

    $data = app( Client::class )->get( 'account' );

    expect( $data )->toBe( [ 'ok' => true ] );
    Http::assertSentCount( 2 );
} );

it( 'throws KitRateLimitException with retryAfter after exhausting retries', function (): void {
    config()->set( 'convertkit.retries', 0 );

    Http::fake( [
        '*' => Http::response( [ 'message' => 'slow down' ], 429, [ 'Retry-After' => '7' ] ),
    ] );

    try {
        app( Client::class )->get( 'account' );
        expect( false )->toBeTrue( 'expected KitRateLimitException to be thrown' );
    } catch ( KitRateLimitException $e ) {
        expect( $e->retryAfter )->toBe( 7 );
        expect( $e->getStatusCode() )->toBe( 429 );
    }

    Http::assertSentCount( 1 );
} );

it( 'refuses to construct with a plain http:// base URL', function (): void {
    new Client(
        http    : app( HttpFactory::class ),
        apiKey  : 'test-key',
        baseUrl : 'http://api.kit.com/v4',
    );
} )->throws( KitException::class, 'must use https://' );

it( 'allows plain http:// when allowInsecureHttp is explicitly enabled', function (): void {
    $client = new Client(
        http              : app( HttpFactory::class ),
        apiKey            : 'test-key',
        baseUrl           : 'http://internal-proxy.test/v4',
        allowInsecureHttp : true,
    );

    expect( $client )->toBeInstanceOf( Client::class );
} );

it( 'surfaces KitRateLimitException immediately when Retry-After exceeds the cap', function (): void {
    config()->set( 'convertkit.retries', 3 );
    config()->set( 'convertkit.max_retry_after', 30 );

    Http::fake( [
        '*' => Http::response( [ 'message' => 'slow down' ], 429, [ 'Retry-After' => '600' ] ),
    ] );

    try {
        app( Client::class )->get( 'account' );
        expect( false )->toBeTrue( 'expected KitRateLimitException to be thrown' );
    } catch ( KitRateLimitException $e ) {
        expect( $e->retryAfter )->toBe( 600 );
    }

    // No retries happened — we bailed on the first oversize Retry-After.
    Http::assertSentCount( 1 );
} );

it( 'caps exponential backoff so a large retries setting cannot balloon into minutes', function (): void {
    $client = new Client(
        http         : app( HttpFactory::class ),
        apiKey       : 'test-key',
        baseUrl      : 'https://api.kit.com/v4',
        retryDelayMs : 500,
        maxBackoffMs : 2_000,
    );

    $reflection = new ReflectionMethod( $client, 'backoffMicroseconds' );

    // Attempt 1: 500ms, 2: 1000ms, 3: 2000ms (at cap), 10: still 2000ms (capped).
    expect( $reflection->invoke( $client, 1 ) )->toBe( 500_000 );
    expect( $reflection->invoke( $client, 3 ) )->toBe( 2_000_000 );
    expect( $reflection->invoke( $client, 10 ) )->toBe( 2_000_000 );
} );

it( 'wraps connection errors in KitServerException', function (): void {
    Http::fake( function (): void {
        throw new Illuminate\Http\Client\ConnectionException( 'dns lookup failed' );
    } );

    app( Client::class )->get( 'account' );
} )->throws( KitServerException::class, 'Failed to reach the Kit API' );
