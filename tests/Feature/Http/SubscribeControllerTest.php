<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Jobs\SubscribeToKit;
use ArtisanPackUI\ConvertKit\Models\KitFeed;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    Bus::fake();

    // Fresh limiter buckets per test.
    app( RateLimiter::class )->clear( 'throttle:10,1' );
} );

it( 'queues a SubscribeToKit job when given a feed_id', function (): void {
    $feed = KitFeed::factory()->create( [
        'kit_form_id' => 55555,
        'kit_tag_ids' => [ 10, 20 ],
        'is_active'   => true,
    ] );

    $response = $this->postJson( 'convertkit/subscribers', [
        'feed_id' => $feed->id,
        'email'   => 'jane@example.com',
        'tags'    => [ 30 ],
    ] );

    $response->assertStatus( 202 );

    Bus::assertDispatched( SubscribeToKit::class, function ( SubscribeToKit $job ) use ( $feed ): bool {
        return 'jane@example.com' === $job->email
            && 55555 === $job->kitFormId
            && [ 10, 20, 30 ] === $job->tagIds
            && SubscribeToKit::class === $job::class // phpstan sanity
            && $feed->id === $feed->id;
    } );
} );

it( 'queues a SubscribeToKit job when given a bare kit_form_id', function (): void {
    $response = $this->postJson( 'convertkit/subscribers', [
        'kit_form_id' => 99999,
        'email'       => 'bob@example.com',
        'first_name'  => 'Bob',
        'fields'      => [ 'company' => 'Acme' ],
        'tags'        => [ 7 ],
    ] );

    $response->assertStatus( 202 );

    Bus::assertDispatched( SubscribeToKit::class, function ( SubscribeToKit $job ): bool {
        return 'bob@example.com' === $job->email
            && 'Bob' === $job->firstName
            && 99999 === $job->kitFormId
            && [ 'company' => 'Acme' ] === $job->fields
            && [ 7 ] === $job->tagIds;
    } );
} );

it( 'rejects requests missing both feed_id and kit_form_id', function (): void {
    $this->postJson( 'convertkit/subscribers', [ 'email' => 'a@b.co' ] )
        ->assertUnprocessable()
        ->assertJsonValidationErrors( [ 'feed_id', 'kit_form_id' ] );

    Bus::assertNothingDispatched();
} );

it( 'rejects requests with both feed_id and kit_form_id', function (): void {
    $feed = KitFeed::factory()->create();

    $this->postJson( 'convertkit/subscribers', [
        'feed_id'     => $feed->id,
        'kit_form_id' => 99999,
        'email'       => 'a@b.co',
    ] )->assertUnprocessable();

    Bus::assertNothingDispatched();
} );

it( 'rejects invalid email', function (): void {
    $this->postJson( 'convertkit/subscribers', [
        'kit_form_id' => 99999,
        'email'       => 'not-an-email',
    ] )
        ->assertUnprocessable()
        ->assertJsonValidationErrors( 'email' );
} );

it( 'returns a validation error when feed_id is unknown or inactive', function (): void {
    $feed = KitFeed::factory()->inactive()->create();

    $this->postJson( 'convertkit/subscribers', [
        'feed_id' => $feed->id,
        'email'   => 'a@b.co',
    ] )->assertUnprocessable()->assertJsonValidationErrors( 'feed_id' );

    Bus::assertNothingDispatched();
} );

it( 'rejects requests with more than 20 tags', function (): void {
    $this->postJson( 'convertkit/subscribers', [
        'kit_form_id' => 99999,
        'email'       => 'a@b.co',
        'tags'        => range( 1, 21 ),
    ] )
        ->assertUnprocessable()
        ->assertJsonValidationErrors( 'tags' );

    Bus::assertNothingDispatched();
} );

it( 'rejects non-string field values', function (): void {
    $this->postJson( 'convertkit/subscribers', [
        'kit_form_id' => 99999,
        'email'       => 'a@b.co',
        'fields'      => [ 'company' => [ 'nested', 'array' ] ],
    ] )
        ->assertUnprocessable()
        ->assertJsonValidationErrors( 'fields.company' );

    Bus::assertNothingDispatched();
} );

it( 'returns a generic validation error when feed_id is unknown or inactive', function (): void {
    // Response shape must be indistinguishable from a normal validation
    // failure so 202 vs 422 does not act as a feed-id enumeration oracle.
    $feed = KitFeed::factory()->inactive()->create();

    $response = $this->postJson( 'convertkit/subscribers', [
        'feed_id' => $feed->id,
        'email'   => 'a@b.co',
    ] );

    $response->assertUnprocessable()->assertJsonValidationErrors( 'feed_id' );
    expect( $response->json( 'message' ) )->toBe( 'The given data was invalid.' );

    Bus::assertNothingDispatched();
} );

it( 'rate limits after the configured attempts per minute', function (): void {
    // Provider registered routes at boot with the default 10 attempts/min.
    $payload = [ 'kit_form_id' => 99999, 'email' => 'rate@example.com' ];

    for ( $i = 0; $i < 10; $i++ ) {
        $this->postJson( 'convertkit/subscribers', $payload )->assertStatus( 202 );
    }

    $this->postJson( 'convertkit/subscribers', $payload )->assertStatus( 429 );
} );
