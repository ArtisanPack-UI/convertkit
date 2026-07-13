<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Facades\ConvertKit;
use ArtisanPackUI\ConvertKit\Jobs\SubscribeToKit;

it( 'subscribes via the forms endpoint when a kit_form_id is set', function (): void {
    $fake = ConvertKit::fake();

    ( new SubscribeToKit(
        email: 'a@b.co',
        firstName: null,
        fields: [],
        tagIds: [ 1, 2 ],
        kitFormId: 555,
    ) )->handle( $fake );

    $fake->assertSubscribed( 'a@b.co', 555 );
    $fake->assertTagged( 'a@b.co', 1 );
    $fake->assertTagged( 'a@b.co', 2 );
} );

it( 'subscribes via the subscribers endpoint and applies tags when kit_form_id is null', function (): void {
    $fake = ConvertKit::fake();

    ( new SubscribeToKit(
        email: 'jane@example.com',
        firstName: 'Jane',
        fields: [ 'company' => 'Acme' ],
        tagIds: [ 10, 20 ],
        kitFormId: null,
    ) )->handle( $fake );

    $fake->assertSubscribed( 'jane@example.com' );
    $fake->assertTagged( 'jane@example.com', 10 );
    $fake->assertTagged( 'jane@example.com', 20 );
} );

it( 'caps tag application at MAX_TAGS_PER_JOB even if the input array is larger', function (): void {
    $fake = ConvertKit::fake();

    // Feed the job 200 tag ids — the cap should slice down to 50 before
    // fanning out. Guards against a buggy dispatcher spraying Kit calls.
    $tagIds = range( 1, 200 );

    ( new SubscribeToKit(
        email: 'a@b.co',
        firstName: null,
        fields: [],
        tagIds: $tagIds,
        kitFormId: null,
    ) )->handle( $fake );

    expect( count( $fake->tagged ) )->toBe( SubscribeToKit::MAX_TAGS_PER_JOB );
    // The cap slices from the front, so tag ids 1..50 land and 51..200 do not.
    $fake->assertTagged( 'a@b.co', SubscribeToKit::MAX_TAGS_PER_JOB );
    expect( fn () => $fake->assertTagged( 'a@b.co', 51 ) )
        ->toThrow( PHPUnit\Framework\AssertionFailedError::class );
} );
