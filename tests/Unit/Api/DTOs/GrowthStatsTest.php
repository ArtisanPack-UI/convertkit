<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Api\DTOs\GrowthStats;

it( 'builds growth stats from a full Kit payload', function (): void {
    $stats = GrowthStats::fromArray( [
        'cancellations'       => 2,
        'net_new_subscribers' => 8,
        'new_subscribers'     => 10,
        'subscribers'         => 150,
        'starting'            => '2023-02-10T00:00:00-05:00',
        'ending'              => '2023-02-24T23:59:59-05:00',
    ] );

    expect( $stats->subscribers )->toBe( 150 );
    expect( $stats->netNewSubscribers )->toBe( 8 );
    expect( $stats->newSubscribers )->toBe( 10 );
    expect( $stats->cancellations )->toBe( 2 );
    expect( $stats->starting )->toBe( '2023-02-10T00:00:00-05:00' );
    expect( $stats->ending )->toBe( '2023-02-24T23:59:59-05:00' );
} );

it( 'defaults missing counts to zero and dates to null', function (): void {
    $stats = GrowthStats::fromArray( [] );

    expect( $stats->subscribers )->toBe( 0 );
    expect( $stats->netNewSubscribers )->toBe( 0 );
    expect( $stats->newSubscribers )->toBe( 0 );
    expect( $stats->cancellations )->toBe( 0 );
    expect( $stats->starting )->toBeNull();
    expect( $stats->ending )->toBeNull();
} );
