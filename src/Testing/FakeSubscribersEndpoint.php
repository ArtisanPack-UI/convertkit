<?php

/**
 * Recording SubscribersEndpoint used by FakeConvertKit.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Testing;

use ArtisanPackUI\ConvertKit\Api\DTOs\Subscriber;
use ArtisanPackUI\ConvertKit\Api\Endpoints\SubscribersEndpoint;

/**
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class FakeSubscribersEndpoint extends SubscribersEndpoint
{
    public function __construct( protected FakeConvertKit $fake )
    {
        // Deliberately skip parent constructor — we never need a Client.
    }

    public function create( string $email, ?string $firstName = null, array $fields = [] ): Subscriber
    {
        return $this->fake->recordSubscribe( $email, $firstName, $fields, null, [] );
    }

    public function find( int $id ): Subscriber
    {
        return new Subscriber( id: $id, email: '', state: 'active' );
    }

    public function findByEmail( string $email ): ?Subscriber
    {
        foreach ( $this->fake->subscribed as $record ) {
            if ( $record['email'] === $email ) {
                return new Subscriber(
                    id        : 0,
                    email     : $email,
                    state     : 'active',
                    firstName : $record['first_name'],
                );
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update( int $id, array $attributes ): Subscriber
    {
        return new Subscriber(
            id        : $id,
            email     : (string) ( $attributes['email_address'] ?? '' ),
            state     : 'active',
            firstName : isset( $attributes['first_name'] ) ? (string) $attributes['first_name'] : null,
            fields    : is_array( $attributes['fields'] ?? null ) ? $attributes['fields'] : [],
        );
    }

    public function tag( int $subscriberId, int $tagId ): void
    {
        $this->fake->recordTag( $subscriberId, $tagId );
    }

    public function untag( int $subscriberId, int $tagId ): void
    {
        $this->fake->recordUntag( $subscriberId, $tagId );
    }

    public function unsubscribe( int $id ): Subscriber
    {
        return new Subscriber( id: $id, email: '', state: 'cancelled' );
    }
}
