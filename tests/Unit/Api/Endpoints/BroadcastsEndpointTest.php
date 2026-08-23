<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Api\DTOs\Broadcast;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitAuthException;
use ArtisanPackUI\ConvertKit\ConvertKit;
use ArtisanPackUI\ConvertKit\EndpointFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * A single Kit `broadcasts/stats` list item.
 *
 * @param  array<string, float|int|string|null>  $stats
 *
 * @return array<string, mixed>
 */
function broadcastFixture( int $id, string $subject, array $stats = [] ): array
{
    return [
        'id'      => $id,
        'subject' => $subject,
        'send_at' => '2023-02-17T11:43:55Z',
        'stats'   => array_merge( [
            'recipients'       => 0,
            'open_rate'        => 0,
            'emails_opened'    => 0,
            'click_rate'       => 0,
            'unsubscribe_rate' => 0,
            'unsubscribes'     => 0,
            'total_clicks'     => 0,
            'status'           => 'completed',
            'progress'         => 1,
        ], $stats ),
    ];
}

/**
 * A full `broadcasts/stats` response wrapping the given items.
 *
 * @param  array<int, array<string, mixed>>  $broadcasts
 *
 * @return array<string, mixed>
 */
function broadcastsResponse( array $broadcasts ): array
{
    return [
        'broadcasts' => $broadcasts,
        'pagination' => [
            'has_previous_page' => false,
            'has_next_page'     => false,
            'start_cursor'      => null,
            'end_cursor'        => null,
            'per_page'          => 500,
        ],
    ];
}

beforeEach( function (): void {
    Cache::store( 'array' )->flush();
} );

it( 'maps the broadcasts stats payload and caches the read', function (): void {
    Http::fake( [
        'api.kit.com/v4/broadcasts/stats*' => Http::response( broadcastsResponse( [
            broadcastFixture( 205, 'Newest', [ 'recipients' => 100, 'open_rate' => 0.5, 'click_rate' => 0.1, 'total_clicks' => 20 ] ),
            broadcastFixture( 204, 'Older', [ 'recipients' => 80 ] ),
        ] ), 200 ),
    ] );

    $endpoint = app( ConvertKit::class )->broadcasts();

    $first  = $endpoint->list();
    $second = $endpoint->list();

    expect( $first )->toHaveCount( 2 );
    expect( $first[0] )->toBeInstanceOf( Broadcast::class );
    expect( $first[0]->subject )->toBe( 'Newest' );
    expect( $first[0]->stats->recipients )->toBe( 100 );
    expect( $first[0]->stats->openRate )->toBe( 0.5 );
    expect( $first[0]->stats->clickRate )->toBe( 0.1 );
    expect( $first[0]->stats->totalClicks )->toBe( 20 );
    expect( $second )->toBe( $first );

    Http::assertSentCount( 1 );
} );

it( 'requests the limit as the per_page query parameter', function (): void {
    Http::fake( [
        'api.kit.com/v4/broadcasts/stats*' => Http::response( broadcastsResponse( [] ), 200 ),
    ] );

    app( ConvertKit::class )->broadcasts()->list( 5 );

    Http::assertSent( fn ( Request $r ): bool => 'GET' === $r->method()
        && str_contains( $r->url(), 'broadcasts/stats' )
        && str_contains( $r->url(), 'per_page=5' ) );
} );

it( 'caches distinct limits under separate keys', function (): void {
    Http::fakeSequence()
        ->push( broadcastsResponse( [ broadcastFixture( 1, 'A' ) ] ), 200 )
        ->push( broadcastsResponse( [ broadcastFixture( 1, 'A' ), broadcastFixture( 2, 'B' ) ] ), 200 );

    $endpoint = app( ConvertKit::class )->broadcasts();

    expect( $endpoint->list( 1 ) )->toHaveCount( 1 );
    expect( $endpoint->list( 2 ) )->toHaveCount( 2 );

    // Re-reading the first limit still serves its cached value.
    expect( $endpoint->list( 1 ) )->toHaveCount( 1 );

    Http::assertSentCount( 2 );
} );

it( 'refreshes the broadcasts cache when refresh() is called', function (): void {
    Http::fakeSequence()
        ->push( broadcastsResponse( [ broadcastFixture( 1, 'A' ) ] ), 200 )
        ->push( broadcastsResponse( [ broadcastFixture( 1, 'A' ), broadcastFixture( 2, 'B' ) ] ), 200 );

    $endpoint = app( ConvertKit::class )->broadcasts();

    expect( $endpoint->list() )->toHaveCount( 1 );
    expect( $endpoint->refresh() )->toHaveCount( 2 );
    expect( $endpoint->list() )->toHaveCount( 2 );

    Http::assertSentCount( 2 );
} );

it( 'honors the configured TTL and re-fetches once it lapses', function (): void {
    config()->set( 'convertkit.cache.broadcasts_ttl', 60 );
    app()->forgetInstance( EndpointFactory::class );
    app()->forgetInstance( ConvertKit::class );

    Http::fakeSequence()
        ->push( broadcastsResponse( [ broadcastFixture( 1, 'A', [ 'recipients' => 10 ] ) ] ), 200 )
        ->push( broadcastsResponse( [ broadcastFixture( 1, 'A', [ 'recipients' => 20 ] ) ] ), 200 );

    $endpoint = app( ConvertKit::class )->broadcasts();

    expect( $endpoint->list()[0]->stats->recipients )->toBe( 10 );
    expect( $endpoint->list()[0]->stats->recipients )->toBe( 10 );
    Http::assertSentCount( 1 );

    $this->travel( 61 )->seconds();

    expect( $endpoint->list()[0]->stats->recipients )->toBe( 20 );
    Http::assertSentCount( 2 );
} );

it( 'propagates auth failures from the list call', function (): void {
    Http::fake( [ '*' => Http::response( [ 'message' => 'nope' ], 401 ) ] );

    app( ConvertKit::class )->broadcasts()->list();
} )->throws( KitAuthException::class );

it( 'rejects a limit below 1 without hitting Kit', function (): void {
    Http::fake();

    expect( fn () => app( ConvertKit::class )->broadcasts()->list( 0 ) )
        ->toThrow( InvalidArgumentException::class );

    Http::assertNothingSent();
} );

it( 'rejects a limit above the cap without hitting Kit', function (): void {
    Http::fake();

    expect( fn () => app( ConvertKit::class )->broadcasts()->list( 101 ) )
        ->toThrow( InvalidArgumentException::class );

    Http::assertNothingSent();
} );
