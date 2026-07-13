<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Facades\ConvertKit;
use ArtisanPackUI\ConvertKit\Testing\FakeConvertKit;
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

it( 'fails assertTagged for an unrecorded tag', function (): void {
    $fake = ConvertKit::fake();

    $s = convertkit()->subscribers()->create( 'a@b.co' );
    convertkit()->subscribers()->tag( $s->id, 1 );

    expect( fn () => $fake->assertTagged( 'a@b.co', 999 ) )
        ->toThrow( AssertionFailedError::class );
} );
