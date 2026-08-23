<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Api\DTOs\BroadcastStats;

it( 'builds broadcast stats from a full Kit payload', function (): void {
    $stats = BroadcastStats::fromArray( [
        'recipients'       => 82,
        'open_rate'        => 0.5,
        'emails_opened'    => 41,
        'click_rate'       => 0.1,
        'unsubscribe_rate' => 0.02,
        'unsubscribes'     => 2,
        'total_clicks'     => 15,
        'progress'         => 1.0,
        'status'           => 'completed',
    ] );

    expect( $stats->recipients )->toBe( 82 );
    expect( $stats->openRate )->toBe( 0.5 );
    expect( $stats->emailsOpened )->toBe( 41 );
    expect( $stats->clickRate )->toBe( 0.1 );
    expect( $stats->unsubscribeRate )->toBe( 0.02 );
    expect( $stats->unsubscribes )->toBe( 2 );
    expect( $stats->totalClicks )->toBe( 15 );
    expect( $stats->progress )->toBe( 1.0 );
    expect( $stats->status )->toBe( 'completed' );
} );

it( 'defaults missing counts and rates to zero and status to null', function (): void {
    $stats = BroadcastStats::fromArray( [] );

    expect( $stats->recipients )->toBe( 0 );
    expect( $stats->openRate )->toBe( 0.0 );
    expect( $stats->emailsOpened )->toBe( 0 );
    expect( $stats->clickRate )->toBe( 0.0 );
    expect( $stats->unsubscribeRate )->toBe( 0.0 );
    expect( $stats->unsubscribes )->toBe( 0 );
    expect( $stats->totalClicks )->toBe( 0 );
    expect( $stats->progress )->toBe( 0.0 );
    expect( $stats->status )->toBeNull();
} );
