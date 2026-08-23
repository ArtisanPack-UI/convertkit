<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Api\DTOs\GrowthStats;
use ArtisanPackUI\ConvertKit\ConvertKit;
use ArtisanPackUI\ConvertKit\EndpointFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * A single Kit `account/growth_stats` fixture response.
 *
 * @param  array<string, int|string>  $overrides
 *
 * @return array{stats: array<string, int|string>}
 */
function growthStatsFixture( array $overrides = [] ): array
{
    return [
        'stats' => array_merge( [
            'cancellations'       => 0,
            'net_new_subscribers' => 3,
            'new_subscribers'     => 3,
            'subscribers'         => 3,
            'starting'            => '2023-02-10T00:00:00-05:00',
            'ending'              => '2023-02-24T23:59:59-05:00',
        ], $overrides ),
    ];
}

beforeEach( function (): void {
    Cache::store( 'array' )->flush();
} );

it( 'maps the growth_stats payload and caches the read', function (): void {
    Http::fake( [
        'api.kit.com/v4/account/growth_stats*' => Http::response( growthStatsFixture( [
            'subscribers'         => 150,
            'net_new_subscribers' => 8,
            'new_subscribers'     => 10,
            'cancellations'       => 2,
        ] ), 200 ),
    ] );

    $account = app( ConvertKit::class )->account();

    $stats = $account->stats();
    expect( $stats )->toBeInstanceOf( GrowthStats::class );
    expect( $stats->subscribers )->toBe( 150 );
    expect( $stats->netNewSubscribers )->toBe( 8 );
    expect( $stats->newSubscribers )->toBe( 10 );
    expect( $stats->cancellations )->toBe( 2 );

    // Second read for the same period is served from cache.
    expect( $account->stats()->subscribers )->toBe( 150 );

    Http::assertSentCount( 1 );
} );

it( 'sends the starting and ending window as query parameters', function (): void {
    Http::fake( [
        'api.kit.com/v4/account/growth_stats*' => Http::response( growthStatsFixture(), 200 ),
    ] );

    app( ConvertKit::class )->account()->stats( '2023-02-01', '2023-02-28' );

    Http::assertSent( fn ( Request $r ): bool => 'GET' === $r->method()
        && str_contains( $r->url(), 'account/growth_stats' )
        && str_contains( $r->url(), 'starting=2023-02-01' )
        && str_contains( $r->url(), 'ending=2023-02-28' ) );
} );

it( 'caches distinct periods under separate keys', function (): void {
    Http::fakeSequence()
        ->push( growthStatsFixture( [ 'subscribers' => 10 ] ), 200 )
        ->push( growthStatsFixture( [ 'subscribers' => 20 ] ), 200 );

    $account = app( ConvertKit::class )->account();

    expect( $account->stats( '2023-01-01', '2023-01-31' )->subscribers )->toBe( 10 );
    expect( $account->stats( '2023-02-01', '2023-02-28' )->subscribers )->toBe( 20 );

    // Re-reading the first period still serves the first cached value.
    expect( $account->stats( '2023-01-01', '2023-01-31' )->subscribers )->toBe( 10 );

    Http::assertSentCount( 2 );
} );

it( 'honors the configured TTL and re-fetches once it lapses', function (): void {
    config()->set( 'convertkit.cache.stats_ttl', 60 );
    app()->forgetInstance( EndpointFactory::class );
    app()->forgetInstance( ConvertKit::class );

    Http::fakeSequence()
        ->push( growthStatsFixture( [ 'subscribers' => 100 ] ), 200 )
        ->push( growthStatsFixture( [ 'subscribers' => 200 ] ), 200 );

    $account = app( ConvertKit::class )->account();

    expect( $account->stats()->subscribers )->toBe( 100 );
    expect( $account->stats()->subscribers )->toBe( 100 );
    Http::assertSentCount( 1 );

    // Once the TTL elapses the next read hits Kit again.
    $this->travel( 61 )->seconds();

    expect( $account->stats()->subscribers )->toBe( 200 );
    Http::assertSentCount( 2 );
} );

it( 'refresh() forces a re-fetch and repopulates the cache', function (): void {
    Http::fakeSequence()
        ->push( growthStatsFixture( [ 'subscribers' => 1 ] ), 200 )
        ->push( growthStatsFixture( [ 'subscribers' => 2 ] ), 200 );

    $account = app( ConvertKit::class )->account();

    expect( $account->stats()->subscribers )->toBe( 1 );

    // refresh() ignores the cached value and stores the fresh one.
    expect( $account->refresh()->subscribers )->toBe( 2 );

    // The next cached read reflects the refreshed value.
    expect( $account->stats()->subscribers )->toBe( 2 );

    Http::assertSentCount( 2 );
} );

it( 'composes a weekly growth time series from per-bucket calls', function (): void {
    Http::fakeSequence()
        ->push( growthStatsFixture( [ 'subscribers' => 10 ] ), 200 )
        ->push( growthStatsFixture( [ 'subscribers' => 20 ] ), 200 )
        ->push( growthStatsFixture( [ 'subscribers' => 30 ] ), 200 );

    $series = app( ConvertKit::class )->account()->growthSeries( '2023-01-01', '2023-01-21', 'week' );

    expect( $series )->toHaveCount( 3 );
    expect( $series[0] )->toBeInstanceOf( GrowthStats::class );
    expect( array_map( fn ( GrowthStats $s ): int => $s->subscribers, $series ) )->toBe( [ 10, 20, 30 ] );

    // One request per weekly bucket, and the whole series is cached.
    Http::assertSentCount( 3 );

    app( ConvertKit::class )->account()->growthSeries( '2023-01-01', '2023-01-21', 'week' );
    Http::assertSentCount( 3 );

    Http::assertSent( fn ( Request $r ): bool => str_contains( $r->url(), 'starting=2023-01-01' )
        && str_contains( $r->url(), 'ending=2023-01-07' ) );
    Http::assertSent( fn ( Request $r ): bool => str_contains( $r->url(), 'starting=2023-01-15' )
        && str_contains( $r->url(), 'ending=2023-01-21' ) );
} );

it( 'parses a bare growth_stats wrapper as well as the stats wrapper', function (): void {
    Http::fake( [
        'api.kit.com/v4/account/growth_stats*' => Http::response( [
            'growth_stats' => [
                'subscribers'         => 77,
                'net_new_subscribers' => 5,
                'new_subscribers'     => 5,
                'cancellations'       => 0,
            ],
        ], 200 ),
    ] );

    expect( app( ConvertKit::class )->account()->stats()->subscribers )->toBe( 77 );
} );

it( 'rejects an unsupported interval', function (): void {
    expect( fn () => app( ConvertKit::class )->account()->growthSeries( '2023-01-01', '2023-01-31', 'fortnight' ) )
        ->toThrow( InvalidArgumentException::class );
} );

it( 'rejects an ending date before the starting date', function (): void {
    expect( fn () => app( ConvertKit::class )->account()->growthSeries( '2023-02-01', '2023-01-01' ) )
        ->toThrow( InvalidArgumentException::class );
} );

it( 'rejects a range that would fan out past the bucket cap without hitting Kit', function (): void {
    Http::fake();

    // Daily buckets across four years is well over the 366-bucket ceiling.
    expect( fn () => app( ConvertKit::class )->account()->growthSeries( '2020-01-01', '2024-01-01', 'day' ) )
        ->toThrow( InvalidArgumentException::class );

    Http::assertNothingSent();
} );

it( 'refreshSeries() forces a re-fetch and repopulates the series cache', function (): void {
    Http::fakeSequence()
        ->push( growthStatsFixture( [ 'subscribers' => 1 ] ), 200 )
        ->push( growthStatsFixture( [ 'subscribers' => 2 ] ), 200 )
        ->push( growthStatsFixture( [ 'subscribers' => 3 ] ), 200 )
        ->push( growthStatsFixture( [ 'subscribers' => 10 ] ), 200 )
        ->push( growthStatsFixture( [ 'subscribers' => 20 ] ), 200 )
        ->push( growthStatsFixture( [ 'subscribers' => 30 ] ), 200 );

    $account = app( ConvertKit::class )->account();

    // Prime the cache with the first three per-bucket reads.
    $series = $account->growthSeries( '2023-01-01', '2023-01-21', 'week' );
    expect( array_map( fn ( GrowthStats $s ): int => $s->subscribers, $series ) )->toBe( [ 1, 2, 3 ] );
    Http::assertSentCount( 3 );

    // A cached read makes no further requests.
    $account->growthSeries( '2023-01-01', '2023-01-21', 'week' );
    Http::assertSentCount( 3 );

    // refreshSeries() ignores the cached series and re-fetches every bucket.
    $refreshed = $account->refreshSeries( '2023-01-01', '2023-01-21', 'week' );
    expect( array_map( fn ( GrowthStats $s ): int => $s->subscribers, $refreshed ) )->toBe( [ 10, 20, 30 ] );
    Http::assertSentCount( 6 );

    // The next cached read reflects the refreshed series.
    $cached = $account->growthSeries( '2023-01-01', '2023-01-21', 'week' );
    expect( array_map( fn ( GrowthStats $s ): int => $s->subscribers, $cached ) )->toBe( [ 10, 20, 30 ] );
    Http::assertSentCount( 6 );
} );

it( 'refreshSeries() validates the range like growthSeries() without hitting Kit', function (): void {
    Http::fake();

    // Daily buckets across four years is well over the 366-bucket ceiling.
    expect( fn () => app( ConvertKit::class )->account()->refreshSeries( '2020-01-01', '2024-01-01', 'day' ) )
        ->toThrow( InvalidArgumentException::class );

    Http::assertNothingSent();
} );

it( 'rejects a malformed date on stats() without forwarding it to Kit', function (): void {
    Http::fake();

    expect( fn () => app( ConvertKit::class )->account()->stats( 'not-a-date' ) )
        ->toThrow( InvalidArgumentException::class );

    expect( fn () => app( ConvertKit::class )->account()->stats( '2023-01-01', 'nonsense' ) )
        ->toThrow( InvalidArgumentException::class );

    Http::assertNothingSent();
} );

it( 'rejects a malformed date on refresh() without forwarding it to Kit', function (): void {
    Http::fake();

    expect( fn () => app( ConvertKit::class )->account()->refresh( 'not-a-date' ) )
        ->toThrow( InvalidArgumentException::class );

    Http::assertNothingSent();
} );
