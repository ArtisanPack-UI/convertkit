<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Models\KitFeed;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    Gate::define( 'manage-convertkit-feeds', fn ( ?object $user, ?KitFeed $feed = null ): bool => true );

    $user = new class extends User {
        protected $guarded = [];
    };

    $this->actingAs( $user );
} );

it( 'reports a matching feed as would_send with the mapped payload', function (): void {
    $feed = KitFeed::factory()->create( [
        'field_map'         => [
            'email_address' => 'email',
            'first_name'    => 'name',
            'company'       => 'company_name',
        ],
        'conditional_logic' => [
            'match'      => 'all',
            'conditions' => [
                [ 'field' => 'email', 'operator' => 'contains', 'value' => '@' ],
            ],
        ],
    ] );

    $response = $this->postJson( "admin/convertkit/feeds/{$feed->id}/test", [
        'values' => [
            'email'        => 'jane@example.com',
            'name'         => 'Jane',
            'company_name' => 'Acme',
        ],
    ] );

    $response->assertOk()
        ->assertJson( [
            'would_send' => true,
            'reason'     => null,
            'payload'    => [
                'email_address' => 'jane@example.com',
                'first_name'    => 'Jane',
                'fields'        => [ 'company' => 'Acme' ],
            ],
        ] );
} );

it( 'reports would_send=false with conditional_logic reason when rules do not match', function (): void {
    $feed = KitFeed::factory()->create( [
        'field_map'         => [ 'email_address' => 'email' ],
        'conditional_logic' => [
            'match'      => 'all',
            'conditions' => [
                [ 'field' => 'plan', 'operator' => 'equals', 'value' => 'pro' ],
            ],
        ],
    ] );

    $response = $this->postJson( "admin/convertkit/feeds/{$feed->id}/test", [
        'values' => [
            'email' => 'jane@example.com',
            'plan'  => 'free',
        ],
    ] );

    $response->assertOk()
        ->assertJson( [
            'would_send' => false,
            'reason'     => 'conditional_logic',
            'payload'    => null,
        ] );
} );

it( 'reports would_send=false with field_map reason when email is missing', function (): void {
    $feed = KitFeed::factory()->create( [
        'field_map'         => [ 'email_address' => 'email' ],
        'conditional_logic' => null,
    ] );

    $response = $this->postJson( "admin/convertkit/feeds/{$feed->id}/test", [
        'values' => [
            'name' => 'Jane',
        ],
    ] );

    $response->assertOk()
        ->assertJson( [
            'would_send' => false,
            'reason'     => 'field_map',
            'payload'    => null,
        ] );
} );

it( 'validates that a values array is provided', function (): void {
    $feed = KitFeed::factory()->create();

    $this->postJson( "admin/convertkit/feeds/{$feed->id}/test", [] )
        ->assertUnprocessable()
        ->assertJsonValidationErrors( 'values' );
} );

it( 'returns 403 when the admin gate denies access', function (): void {
    Gate::define( 'manage-convertkit-feeds', fn (): bool => false );

    $feed = KitFeed::factory()->create();

    $this->postJson( "admin/convertkit/feeds/{$feed->id}/test", [ 'values' => [ 'email' => 'a@b.co' ] ] )
        ->assertForbidden();
} );
