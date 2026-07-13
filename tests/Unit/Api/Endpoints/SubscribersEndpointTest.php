<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Api\DTOs\Subscriber;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitNotFoundException;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitValidationException;
use ArtisanPackUI\ConvertKit\ConvertKit;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it( 'creates a subscriber', function (): void {
    Http::fake( [
        'api.kit.com/v4/subscribers' => Http::response( [
            'subscriber' => [
                'id'            => 1,
                'email_address' => 'jane@example.com',
                'first_name'    => 'Jane',
                'state'         => 'active',
            ],
        ], 201 ),
    ] );

    $sub = app( ConvertKit::class )->subscribers()->create( 'jane@example.com', 'Jane' );

    expect( $sub )->toBeInstanceOf( Subscriber::class );
    expect( $sub->id )->toBe( 1 );
    expect( $sub->firstName )->toBe( 'Jane' );

    Http::assertSent( fn ( Request $r ): bool =>
        'POST' === $r->method()
        && 'jane@example.com' === $r['email_address']
        && 'Jane' === $r['first_name'],
    );
} );

it( 'finds a subscriber by ID', function (): void {
    Http::fake( [
        'api.kit.com/v4/subscribers/42' => Http::response( [
            'subscriber' => [ 'id' => 42, 'email_address' => 'a@b.com', 'state' => 'active' ],
        ], 200 ),
    ] );

    $sub = app( ConvertKit::class )->subscribers()->find( 42 );

    expect( $sub->id )->toBe( 42 );
} );

it( 'returns null when findByEmail has no match', function (): void {
    Http::fake( [
        'api.kit.com/v4/subscribers*' => Http::response( [ 'subscribers' => [] ], 200 ),
    ] );

    expect( app( ConvertKit::class )->subscribers()->findByEmail( 'nobody@example.com' ) )->toBeNull();
} );

it( 'returns the first match when findByEmail hits', function (): void {
    Http::fake( [
        'api.kit.com/v4/subscribers*' => Http::response( [
            'subscribers' => [
                [ 'id' => 7, 'email_address' => 'a@b.com', 'state' => 'active' ],
            ],
        ], 200 ),
    ] );

    $sub = app( ConvertKit::class )->subscribers()->findByEmail( 'a@b.com' );

    expect( $sub )->not->toBeNull();
    expect( $sub->id )->toBe( 7 );
} );

it( 'updates a subscriber', function (): void {
    Http::fake( [
        'api.kit.com/v4/subscribers/9' => Http::response( [
            'subscriber' => [ 'id' => 9, 'email_address' => 'new@b.com', 'state' => 'active', 'first_name' => 'New' ],
        ], 200 ),
    ] );

    $sub = app( ConvertKit::class )->subscribers()->update( 9, [ 'first_name' => 'New' ] );

    expect( $sub->firstName )->toBe( 'New' );
    Http::assertSent( fn ( Request $r ): bool => 'PUT' === $r->method() );
} );

it( 'tags and untags a subscriber', function (): void {
    Http::fake( [
        'api.kit.com/v4/tags/5/subscribers/9' => Http::response( [], 200 ),
    ] );

    $endpoint = app( ConvertKit::class )->subscribers();
    $endpoint->tag( 9, 5 );
    $endpoint->untag( 9, 5 );

    Http::assertSent( fn ( Request $r ): bool => 'POST' === $r->method() && str_ends_with( $r->url(), '/tags/5/subscribers/9' ) );
    Http::assertSent( fn ( Request $r ): bool => 'DELETE' === $r->method() && str_ends_with( $r->url(), '/tags/5/subscribers/9' ) );
} );

it( 'unsubscribes a subscriber', function (): void {
    Http::fake( [
        'api.kit.com/v4/subscribers/9/unsubscribe' => Http::response( [
            'subscriber' => [ 'id' => 9, 'email_address' => 'a@b.com', 'state' => 'cancelled' ],
        ], 200 ),
    ] );

    $sub = app( ConvertKit::class )->subscribers()->unsubscribe( 9 );

    expect( $sub->state )->toBe( 'cancelled' );
} );

it( 'bubbles up KitValidationException on bad create', function (): void {
    Http::fake( [ '*' => Http::response( [ 'message' => 'invalid', 'errors' => [ 'foo' ] ], 422 ) ] );

    app( ConvertKit::class )->subscribers()->create( 'bad' );
} )->throws( KitValidationException::class );

it( 'bubbles up KitNotFoundException on missing find', function (): void {
    Http::fake( [ '*' => Http::response( [ 'message' => 'gone' ], 404 ) ] );

    app( ConvertKit::class )->subscribers()->find( 999 );
} )->throws( KitNotFoundException::class );
