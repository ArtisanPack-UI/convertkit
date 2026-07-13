<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Api\DTOs\CustomField;
use ArtisanPackUI\ConvertKit\ConvertKit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it( 'caches custom fields list', function (): void {
    Cache::store( 'array' )->flush();

    Http::fake( [
        'api.kit.com/v4/custom_fields' => Http::response( [
            'custom_fields' => [
                [ 'id' => 1, 'label' => 'Company', 'key' => 'company' ],
            ],
        ], 200 ),
    ] );

    $endpoint = app( ConvertKit::class )->customFields();

    $first  = $endpoint->list();
    $second = $endpoint->list();

    expect( $first )->toHaveCount( 1 );
    expect( $first[0] )->toBeInstanceOf( CustomField::class );
    expect( $first[0]->key )->toBe( 'company' );
    expect( $second )->toBe( $first );

    Http::assertSentCount( 1 );
} );

it( 'creates a custom field and invalidates the cache', function (): void {
    Cache::store( 'array' )->flush();

    Http::fakeSequence()
        ->push( [ 'custom_fields' => [ [ 'id' => 1, 'label' => 'Company', 'key' => 'company' ] ] ], 200 )
        ->push( [ 'custom_field' => [ 'id' => 2, 'label' => 'Role', 'key' => 'role' ] ], 201 )
        ->push( [ 'custom_fields' => [
            [ 'id' => 1, 'label' => 'Company', 'key' => 'company' ],
            [ 'id' => 2, 'label' => 'Role', 'key' => 'role' ],
        ] ], 200 );

    $endpoint = app( ConvertKit::class )->customFields();

    $endpoint->list();
    $new = $endpoint->create( 'Role' );

    expect( $new->key )->toBe( 'role' );
    expect( $endpoint->list() )->toHaveCount( 2 );

    Http::assertSentCount( 3 );
} );
