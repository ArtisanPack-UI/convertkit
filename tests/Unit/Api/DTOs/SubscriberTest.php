<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Api\DTOs\Subscriber;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitException;

it( 'builds a Subscriber from a full Kit payload', function (): void {
    $sub = Subscriber::fromArray( [
        'id'            => 42,
        'email_address' => 'jane@example.com',
        'state'         => 'active',
        'first_name'    => 'Jane',
        'created_at'    => '2026-01-01T00:00:00Z',
        'fields'        => [ 'company' => 'Acme' ],
    ] );

    expect( $sub->id )->toBe( 42 );
    expect( $sub->email )->toBe( 'jane@example.com' );
    expect( $sub->firstName )->toBe( 'Jane' );
    expect( $sub->fields )->toBe( [ 'company' => 'Acme' ] );
} );

it( 'throws when the payload is missing the id field', function (): void {
    Subscriber::fromArray( [ 'email_address' => 'orphan@example.com', 'state' => 'active' ] );
} )->throws( KitException::class, 'missing the required `id`' );

it( 'throws when id is present but non-numeric', function (): void {
    Subscriber::fromArray( [ 'id' => 'not-a-number', 'email_address' => 'x@y.com', 'state' => 'active' ] );
} )->throws( KitException::class );
