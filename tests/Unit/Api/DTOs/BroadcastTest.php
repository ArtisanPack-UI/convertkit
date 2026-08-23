<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Api\DTOs\Broadcast;
use ArtisanPackUI\ConvertKit\Api\DTOs\BroadcastStats;

it( 'builds a broadcast and its nested stats from a Kit list item', function (): void {
    $broadcast = Broadcast::fromArray( [
        'id'      => 205,
        'subject' => '... or will you',
        'send_at' => '2023-02-17T11:43:55Z',
        'stats'   => [
            'recipients'   => 100,
            'open_rate'    => 0.42,
            'click_rate'   => 0.08,
            'total_clicks' => 12,
            'status'       => 'completed',
        ],
    ] );

    expect( $broadcast->id )->toBe( 205 );
    expect( $broadcast->subject )->toBe( '... or will you' );
    expect( $broadcast->sendAt )->toBe( '2023-02-17T11:43:55Z' );
    expect( $broadcast->stats )->toBeInstanceOf( BroadcastStats::class );
    expect( $broadcast->stats->recipients )->toBe( 100 );
    expect( $broadcast->stats->openRate )->toBe( 0.42 );
    expect( $broadcast->stats->totalClicks )->toBe( 12 );
    expect( $broadcast->stats->status )->toBe( 'completed' );
} );

it( 'defaults a draft with no send date and no stats block', function (): void {
    $broadcast = Broadcast::fromArray( [
        'id'      => 3,
        'subject' => 'Campaign subject 3',
        'send_at' => null,
    ] );

    expect( $broadcast->id )->toBe( 3 );
    expect( $broadcast->sendAt )->toBeNull();
    expect( $broadcast->stats )->toBeInstanceOf( BroadcastStats::class );
    expect( $broadcast->stats->recipients )->toBe( 0 );
    expect( $broadcast->stats->status )->toBeNull();
} );
