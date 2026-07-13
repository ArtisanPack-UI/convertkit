<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Api\DTOs\Form;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitAuthException;
use ArtisanPackUI\ConvertKit\ConvertKit;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it( 'caches the forms list on first read', function (): void {
    Cache::store( 'array' )->flush();

    Http::fake( [
        'api.kit.com/v4/forms' => Http::response( [
            'forms' => [
                [ 'id' => 1, 'name' => 'Newsletter', 'type' => 'embed', 'format' => 'inline' ],
                [ 'id' => 2, 'name' => 'Popup' ],
            ],
        ], 200 ),
    ] );

    $endpoint = app( ConvertKit::class )->forms();

    $first  = $endpoint->list();
    $second = $endpoint->list();

    expect( $first )->toHaveCount( 2 );
    expect( $first[0] )->toBeInstanceOf( Form::class );
    expect( $first[0]->name )->toBe( 'Newsletter' );
    expect( $second )->toBe( $first );

    Http::assertSentCount( 1 );
} );

it( 'refreshes the forms cache when refresh() is called', function (): void {
    Cache::store( 'array' )->flush();

    Http::fakeSequence()
        ->push( [ 'forms' => [ [ 'id' => 1, 'name' => 'A' ] ] ], 200 )
        ->push( [ 'forms' => [ [ 'id' => 1, 'name' => 'A' ], [ 'id' => 2, 'name' => 'B' ] ] ], 200 );

    $endpoint = app( ConvertKit::class )->forms();

    expect( $endpoint->list() )->toHaveCount( 1 );
    expect( $endpoint->refresh() )->toHaveCount( 2 );
    expect( $endpoint->list() )->toHaveCount( 2 );

    Http::assertSentCount( 2 );
} );

it( 'subscribes an email to a form', function (): void {
    Http::fake( [
        'api.kit.com/v4/forms/1/subscribers' => Http::response( [
            'subscriber' => [ 'id' => 100, 'email_address' => 'x@y.com', 'state' => 'active' ],
        ], 200 ),
    ] );

    $sub = app( ConvertKit::class )->forms()->subscribe( 1, 'x@y.com', [ 'foo' => 'bar' ], [ 5, 6 ] );

    expect( $sub->id )->toBe( 100 );

    Http::assertSent( fn ( Request $r ): bool =>
        'POST' === $r->method()
        && 'x@y.com' === $r['email_address']
        && [ 'foo' => 'bar' ] === $r['fields']
        && [ 5, 6 ] === $r['tags'],
    );
} );

it( 'propagates auth failures from the list call', function (): void {
    Cache::store( 'array' )->flush();
    Http::fake( [ '*' => Http::response( [ 'message' => 'nope' ], 401 ) ] );

    app( ConvertKit::class )->forms()->list();
} )->throws( KitAuthException::class );
