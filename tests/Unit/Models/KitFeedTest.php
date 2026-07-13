<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Models\KitFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses( RefreshDatabase::class );

it( 'casts JSON columns to arrays', function (): void {
    $feed = KitFeed::factory()->create( [
        'kit_tag_ids'       => [ 100, 200 ],
        'field_map'         => [ 'email_address' => 'email', 'first_name' => 'name' ],
        'conditional_logic' => [ 'match' => 'all', 'conditions' => [] ],
    ] );

    $fresh = $feed->fresh();

    expect( $fresh->kit_tag_ids )->toBe( [ 100, 200 ] );
    expect( $fresh->field_map )->toBe( [ 'email_address' => 'email', 'first_name' => 'name' ] );
    expect( $fresh->conditional_logic )->toBe( [ 'match' => 'all', 'conditions' => [] ] );
    expect( $fresh->is_active )->toBeTrue();
} );

it( 'stores conditional_logic as null when unset', function (): void {
    $feed = KitFeed::factory()->create( [ 'conditional_logic' => null ] );

    expect( $feed->fresh()->conditional_logic )->toBeNull();
} );

it( 'enforces unique feed name per form via the DB constraint', function (): void {
    KitFeed::factory()->create( [ 'form_id' => 42, 'name' => 'Newsletter' ] );

    KitFeed::factory()->create( [ 'form_id' => 42, 'name' => 'Newsletter' ] );
} )->throws( Illuminate\Database\QueryException::class );

it( 'allows the same feed name across different forms', function (): void {
    KitFeed::factory()->create( [ 'form_id' => 1, 'name' => 'Newsletter' ] );
    $other = KitFeed::factory()->create( [ 'form_id' => 2, 'name' => 'Newsletter' ] );

    expect( $other->exists )->toBeTrue();
} );

it( 'defaults kit_tag_ids to an empty array when not provided at create', function (): void {
    // Simulates the store request omitting kit_tag_ids — the column is
    // NOT NULL in the migration, so the model default must kick in.
    $feed = KitFeed::create( [
        'form_id'   => 1,
        'name'      => 'No tags',
        'field_map' => [ 'email_address' => 'email' ],
    ] );

    expect( $feed->fresh()->kit_tag_ids )->toBe( [] );
} );

it( 'throws a descriptive error when form() is called with a missing model class', function (): void {
    config()->set( 'convertkit.forms_integration.form_model', 'App\\Models\\DefinitelyNotAClass' );

    $feed = KitFeed::factory()->create();

    expect( fn () => $feed->form() )->toThrow( RuntimeException::class, 'DefinitelyNotAClass' );
} );

it( 'throws when form() is called with an empty model class config', function (): void {
    config()->set( 'convertkit.forms_integration.form_model', '' );

    $feed = KitFeed::factory()->create();

    expect( fn () => $feed->form() )->toThrow( RuntimeException::class );
} );
