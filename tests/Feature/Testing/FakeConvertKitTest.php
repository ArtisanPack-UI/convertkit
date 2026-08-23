<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Api\DTOs\Broadcast;
use ArtisanPackUI\ConvertKit\Api\DTOs\BroadcastStats;
use ArtisanPackUI\ConvertKit\Api\DTOs\GrowthStats;
use ArtisanPackUI\ConvertKit\Facades\ConvertKit;
use ArtisanPackUI\ConvertKit\Testing\FakeConvertKit;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;

it( 'swaps the container binding and returns a FakeConvertKit', function (): void {
    $fake = ConvertKit::fake();

    expect( $fake )->toBeInstanceOf( FakeConvertKit::class );
    expect( app( 'convertkit' ) )->toBe( $fake );
    expect( app( ArtisanPackUI\ConvertKit\ConvertKit::class ) )->toBe( $fake );
} );

it( 'records subscribers()->create() calls', function (): void {
    $fake = ConvertKit::fake();

    convertkit()->subscribers()->create( 'jane@example.com', 'Jane', [ 'company' => 'Acme' ] );

    $fake->assertSubscribed( 'jane@example.com' );
    $fake->assertSentCount( 1 );
} );

it( 'records forms()->subscribe() calls with the form id', function (): void {
    $fake = ConvertKit::fake();

    convertkit()->forms()->subscribe( 12345, 'bob@example.com', [], [ 7 ] );

    $fake->assertSubscribed( 'bob@example.com', 12345 );
    $fake->assertTagged( 'bob@example.com', 7 );
} );

it( 'records standalone tag calls', function (): void {
    $fake = ConvertKit::fake();

    $subscriber = convertkit()->subscribers()->create( 'alice@example.com' );
    convertkit()->subscribers()->tag( $subscriber->id, 42 );

    $fake->assertTagged( 'alice@example.com', 42 );
} );

it( 'passes assertNothingSent when idle', function (): void {
    $fake = ConvertKit::fake();

    $fake->assertNothingSent();
} );

it( 'fails assertNothingSent after a subscribe', function (): void {
    $fake = ConvertKit::fake();

    convertkit()->subscribers()->create( 'x@y.co' );

    expect( fn () => $fake->assertNothingSent() )
        ->toThrow( AssertionFailedError::class );
} );

it( 'fails assertSubscribed for an unknown email', function (): void {
    $fake = ConvertKit::fake();

    convertkit()->subscribers()->create( 'a@b.co' );

    expect( fn () => $fake->assertSubscribed( 'nobody@example.com' ) )
        ->toThrow( AssertionFailedError::class );
} );

it( 'fails assertSubscribed when the form id does not match', function (): void {
    $fake = ConvertKit::fake();

    convertkit()->forms()->subscribe( 111, 'a@b.co' );

    expect( fn () => $fake->assertSubscribed( 'a@b.co', 222 ) )
        ->toThrow( AssertionFailedError::class );
} );

it( 'exposes a fake account endpoint that never hits the network', function (): void {
    ConvertKit::fake();
    Http::fake();

    $stats  = convertkit()->account()->stats();
    $series = convertkit()->account()->growthSeries( '2023-01-01', '2023-01-31' );

    expect( $stats )->toBeInstanceOf( GrowthStats::class );
    expect( $stats->subscribers )->toBe( 0 );

    // The fake mirrors the real endpoint's contract: one zero-valued point per
    // weekly bucket ( Jan 1-31 => 5 buckets ), not an empty array.
    expect( $series )->toHaveCount( 5 );
    expect( $series[0] )->toBeInstanceOf( GrowthStats::class );
    expect( $series[0]->subscribers )->toBe( 0 );
    expect( $series[0]->starting )->toBe( '2023-01-01' );

    Http::assertNothingSent();
} );

it( 'fails assertTagged for an unrecorded tag', function (): void {
    $fake = ConvertKit::fake();

    $s = convertkit()->subscribers()->create( 'a@b.co' );
    convertkit()->subscribers()->tag( $s->id, 1 );

    expect( fn () => $fake->assertTagged( 'a@b.co', 999 ) )
        ->toThrow( AssertionFailedError::class );
} );

it( 'exposes a fake broadcasts endpoint that never hits the network', function (): void {
    ConvertKit::fake();
    Http::fake();

    expect( convertkit()->broadcasts()->list() )->toBe( [] );
    expect( convertkit()->broadcasts()->refresh( 5 ) )->toBe( [] );

    Http::assertNothingSent();
} );

it( 'returns seeded stats fixtures with the requested window echoed', function (): void {
    $fake = ConvertKit::fake();
    Http::fake();

    $fake->fakeStats( subscribers: 1200, netNewSubscribers: 45, newSubscribers: 60, cancellations: 15 );

    $stats = convertkit()->account()->stats( '2023-02-01', '2023-02-28' );

    expect( $stats->subscribers )->toBe( 1200 );
    expect( $stats->netNewSubscribers )->toBe( 45 );
    expect( $stats->newSubscribers )->toBe( 60 );
    expect( $stats->cancellations )->toBe( 15 );
    expect( $stats->starting )->toBe( '2023-02-01' );
    expect( $stats->ending )->toBe( '2023-02-28' );

    Http::assertNothingSent();
} );

it( 'accepts a ready-made GrowthStats fixture and echoes the window at read time', function (): void {
    $fake = ConvertKit::fake();

    $fake->fakeStats( new GrowthStats( 500, 10, 10, 0, '1999-01-01', '1999-12-31' ) );

    $stats = convertkit()->account()->refresh( '2024-06-01', '2024-06-30' );

    expect( $stats->subscribers )->toBe( 500 );
    expect( $stats->starting )->toBe( '2024-06-01' );
    expect( $stats->ending )->toBe( '2024-06-30' );
} );

it( 'returns a seeded growth series verbatim', function (): void {
    $fake = ConvertKit::fake();
    Http::fake();

    $fake->fakeGrowthSeries(
        new GrowthStats( 100, 5, 5, 0, '2023-01-01', '2023-01-07' ),
        new GrowthStats( 110, 10, 12, 2, '2023-01-08', '2023-01-14' ),
    );

    $series = convertkit()->account()->growthSeries( '2023-01-01', '2023-01-31' );

    expect( $series )->toHaveCount( 2 );
    expect( $series[1]->subscribers )->toBe( 110 );
    expect( $series[1]->netNewSubscribers )->toBe( 10 );

    Http::assertNothingSent();
} );

it( 'still validates the range for a seeded growth series', function (): void {
    $fake = ConvertKit::fake();
    $fake->fakeGrowthSeries( new GrowthStats( 1, 0, 0, 0 ) );

    expect( fn () => convertkit()->account()->growthSeries( '2023-02-01', '2023-01-01' ) )
        ->toThrow( InvalidArgumentException::class );
} );

it( 'asserts stats and growth series were requested', function (): void {
    $fake = ConvertKit::fake();

    convertkit()->account()->stats( '2023-01-01', '2023-01-31' );
    convertkit()->account()->growthSeries( '2023-01-01', '2023-01-31', 'week' );

    $fake->assertStatsRequested();
    $fake->assertStatsRequested( '2023-01-01', '2023-01-31' );
    $fake->assertGrowthSeriesRequested();
    $fake->assertGrowthSeriesRequested( '2023-01-01', '2023-01-31', 'week' );
} );

it( 'fails assertStatsRequested when the window does not match', function (): void {
    $fake = ConvertKit::fake();

    convertkit()->account()->stats( '2023-01-01', '2023-01-31' );

    expect( fn () => $fake->assertStatsRequested( '2020-01-01', '2020-12-31' ) )
        ->toThrow( AssertionFailedError::class );
} );

it( 'fails assertStatsRequested when no stats were requested', function (): void {
    $fake = ConvertKit::fake();

    expect( fn () => $fake->assertStatsRequested() )
        ->toThrow( AssertionFailedError::class );
} );

it( 'fails assertGrowthSeriesRequested when the interval does not match', function (): void {
    $fake = ConvertKit::fake();

    convertkit()->account()->growthSeries( '2023-01-01', '2023-01-31', 'week' );

    expect( fn () => $fake->assertGrowthSeriesRequested( interval: 'month' ) )
        ->toThrow( AssertionFailedError::class );
} );

it( 'fails assertGrowthSeriesRequested when no series was requested', function (): void {
    $fake = ConvertKit::fake();

    expect( fn () => $fake->assertGrowthSeriesRequested() )
        ->toThrow( AssertionFailedError::class );
} );

it( 'fails assertBroadcastsListed when nothing was listed', function (): void {
    $fake = ConvertKit::fake();

    expect( fn () => $fake->assertBroadcastsListed() )
        ->toThrow( AssertionFailedError::class );
} );

it( 'returns seeded broadcasts with open and click stats, sliced to the limit', function (): void {
    $fake = ConvertKit::fake();
    Http::fake();

    $fake->fakeBroadcasts(
        new Broadcast( 3, 'Newest', new BroadcastStats( 1000, 500, 0.5, 200, 0.2, 5, 0.005, 1.0, 'completed' ), '2023-03-03' ),
        new Broadcast( 2, 'Middle', new BroadcastStats( 900, 360, 0.4, 90, 0.1, 3, 0.003, 1.0, 'completed' ), '2023-02-02' ),
        new Broadcast( 1, 'Oldest', new BroadcastStats( 800, 240, 0.3, 40, 0.05, 2, 0.0025, 1.0, 'completed' ), '2023-01-01' ),
    );

    $all = convertkit()->broadcasts()->list();
    expect( $all )->toHaveCount( 3 );
    expect( $all[0]->subject )->toBe( 'Newest' );
    expect( $all[0]->stats->openRate )->toBe( 0.5 );
    expect( $all[0]->stats->clickRate )->toBe( 0.2 );

    $limited = convertkit()->broadcasts()->list( 2 );
    expect( $limited )->toHaveCount( 2 );
    expect( $limited[1]->subject )->toBe( 'Middle' );

    Http::assertNothingSent();
} );

it( 'asserts broadcasts were listed', function (): void {
    $fake = ConvertKit::fake();

    convertkit()->broadcasts()->list( 5 );

    $fake->assertBroadcastsListed();
    $fake->assertBroadcastsListed( 5 );
} );

it( 'fails assertBroadcastsListed when the limit does not match', function (): void {
    $fake = ConvertKit::fake();

    convertkit()->broadcasts()->list( 5 );

    expect( fn () => $fake->assertBroadcastsListed( 10 ) )
        ->toThrow( AssertionFailedError::class );
} );

it( 'validates the broadcasts limit like the real endpoint', function (): void {
    ConvertKit::fake();

    expect( fn () => convertkit()->broadcasts()->list( 0 ) )
        ->toThrow( InvalidArgumentException::class );
} );
