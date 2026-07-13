<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Api\DTOs\Tag;
use ArtisanPackUI\ConvertKit\ConvertKit;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it( 'caches tags list and creates new tags with cache invalidation', function (): void {
    Cache::store( 'array' )->flush();

    Http::fakeSequence()
        ->push( [ 'tags' => [ [ 'id' => 1, 'name' => 'newsletter' ] ] ], 200 )
        ->push( [ 'tag' => [ 'id' => 2, 'name' => 'launch' ] ], 201 )
        ->push( [ 'tags' => [
            [ 'id' => 1, 'name' => 'newsletter' ],
            [ 'id' => 2, 'name' => 'launch' ],
        ] ], 200 );

    $endpoint = app( ConvertKit::class )->tags();

    $first = $endpoint->list();
    expect( $first )->toHaveCount( 1 );
    expect( $first[0] )->toBeInstanceOf( Tag::class );

    // Second list() serves from cache.
    expect( $endpoint->list() )->toHaveCount( 1 );

    $new = $endpoint->create( 'launch' );
    expect( $new->id )->toBe( 2 );

    // create() invalidated the cache, so the next list() re-fetches.
    expect( $endpoint->list() )->toHaveCount( 2 );

    Http::assertSentCount( 3 );
} );

it( 'applies and removes a tag on a subscriber', function (): void {
    Http::fake( [
        'api.kit.com/v4/tags/5/subscribers/10' => Http::response( [], 200 ),
    ] );

    $endpoint = app( ConvertKit::class )->tags();
    $endpoint->apply( 5, 10 );
    $endpoint->remove( 5, 10 );

    Http::assertSent( fn ( Request $r ): bool => 'POST' === $r->method() );
    Http::assertSent( fn ( Request $r ): bool => 'DELETE' === $r->method() );
} );
