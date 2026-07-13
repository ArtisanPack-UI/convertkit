<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Models\KitFeed;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses( RefreshDatabase::class );

/**
 * Sign in a stub user + grant the default admin ability so happy-path
 * tests can hit the CRUD endpoints. The gate now fails closed on empty
 * config, so we cannot rely on the "empty ability = skip" bypass that
 * an earlier version of the controller shipped.
 */
beforeEach( function (): void {
    Gate::define( 'manage-convertkit-feeds', fn ( ?object $user, ?KitFeed $feed = null ): bool => true );

    $user = new class extends User {
        protected $guarded = [];
    };

    $this->actingAs( $user );
} );

it( 'lists feeds', function (): void {
    KitFeed::factory()->count( 2 )->create();

    $response = $this->getJson( 'admin/convertkit/feeds' );

    $response->assertOk()->assertJsonCount( 2, 'data' );
} );

it( 'filters feeds by form_id', function (): void {
    KitFeed::factory()->create( [ 'form_id' => 1, 'name' => 'A' ] );
    KitFeed::factory()->create( [ 'form_id' => 2, 'name' => 'B' ] );

    $response = $this->getJson( 'admin/convertkit/feeds?form_id=1' );

    $response->assertOk()
        ->assertJsonCount( 1, 'data' )
        ->assertJsonPath( 'data.0.name', 'A' );
} );

it( 'creates a feed', function (): void {
    $payload = [
        'form_id'     => 5,
        'name'        => 'Newsletter',
        'kit_form_id' => 12345,
        'kit_tag_ids' => [ 100, 200 ],
        'field_map'   => [ 'email_address' => 'email', 'first_name' => 'name' ],
    ];

    $response = $this->postJson( 'admin/convertkit/feeds', $payload );

    $response->assertCreated()
        ->assertJsonPath( 'data.name', 'Newsletter' )
        ->assertJsonPath( 'data.kit_tag_ids', [ 100, 200 ] );

    expect( KitFeed::where( 'name', 'Newsletter' )->exists() )->toBeTrue();
} );

it( 'creates a feed with no kit_tag_ids and defaults to an empty array', function (): void {
    $response = $this->postJson( 'admin/convertkit/feeds', [
        'form_id'   => 5,
        'name'      => 'No Tags',
        'field_map' => [ 'email_address' => 'email' ],
    ] );

    $response->assertCreated()->assertJsonPath( 'data.kit_tag_ids', [] );
} );

it( 'rejects a store request missing email_address in the field map', function (): void {
    $response = $this->postJson( 'admin/convertkit/feeds', [
        'form_id'   => 5,
        'name'      => 'No Email',
        'field_map' => [ 'first_name' => 'name' ],
    ] );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors( 'field_map.email_address' );
} );

it( 'rejects an unknown conditional operator', function (): void {
    $response = $this->postJson( 'admin/convertkit/feeds', [
        'form_id'           => 5,
        'name'              => 'Bad Op',
        'field_map'         => [ 'email_address' => 'email' ],
        'conditional_logic' => [
            'match'      => 'all',
            'conditions' => [
                [ 'field' => 'x', 'operator' => 'starts_with', 'value' => 'a' ],
            ],
        ],
    ] );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors( 'conditional_logic.conditions.0.operator' );
} );

it( 'shows a single feed', function (): void {
    $feed = KitFeed::factory()->create();

    $this->getJson( "admin/convertkit/feeds/{$feed->id}" )
        ->assertOk()
        ->assertJsonPath( 'data.id', $feed->id );
} );

it( 'updates a feed', function (): void {
    $feed = KitFeed::factory()->create( [ 'name' => 'Old' ] );

    $response = $this->putJson( "admin/convertkit/feeds/{$feed->id}", [ 'name' => 'New' ] );

    $response->assertOk()->assertJsonPath( 'data.name', 'New' );
    expect( $feed->fresh()->name )->toBe( 'New' );
} );

it( 'deletes a feed', function (): void {
    $feed = KitFeed::factory()->create();

    $this->deleteJson( "admin/convertkit/feeds/{$feed->id}" )->assertNoContent();

    expect( KitFeed::find( $feed->id ) )->toBeNull();
} );

it( 'returns 403 when the admin gate denies access', function (): void {
    Gate::define( 'manage-convertkit-feeds', fn (): bool => false );

    $this->getJson( 'admin/convertkit/feeds' )->assertForbidden();
} );

it( 'still enforces the default gate when config value is blank', function (): void {
    // Empty config value must not open the API — controller falls back
    // to the built-in `manage-convertkit-feeds` ability.
    config()->set( 'convertkit.feed_admin.gate_ability', '' );
    Gate::define( 'manage-convertkit-feeds', fn (): bool => false );

    $this->getJson( 'admin/convertkit/feeds' )->assertForbidden();
} );

it( 'passes the resolved feed to the gate closure for per-record scoping', function (): void {
    $ownFeed   = KitFeed::factory()->create( [ 'form_id' => 100 ] );
    $otherFeed = KitFeed::factory()->create( [ 'form_id' => 200 ] );

    Gate::define(
        'manage-convertkit-feeds',
        fn ( ?object $user, ?KitFeed $feed = null ): bool => null === $feed || 100 === $feed->form_id,
    );

    $this->getJson( "admin/convertkit/feeds/{$ownFeed->id}" )->assertOk();
    $this->getJson( "admin/convertkit/feeds/{$otherFeed->id}" )->assertForbidden();
} );
