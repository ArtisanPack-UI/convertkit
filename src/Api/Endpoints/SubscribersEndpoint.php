<?php

/**
 * Kit v4 subscribers endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Api\Endpoints;

use ArtisanPackUI\ConvertKit\Api\Client;
use ArtisanPackUI\ConvertKit\Api\DTOs\PaginatedCollection;
use ArtisanPackUI\ConvertKit\Api\DTOs\Subscriber;

/**
 * Subscribers endpoint.
 *
 * Wraps the Kit v4 `/subscribers` endpoints and returns typed `Subscriber`
 * DTOs (or `PaginatedCollection<Subscriber>` for list-style calls).
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class SubscribersEndpoint
{
    public function __construct( protected Client $client )
    {
    }

    /**
     * Create a subscriber.
     *
     * @param  array<string, mixed>  $fields  Optional custom field values keyed by field key.
     */
    public function create( string $email, ?string $firstName = null, array $fields = [] ): Subscriber
    {
        $payload = [ 'email_address' => $email ];

        if ( null !== $firstName ) {
            $payload['first_name'] = $firstName;
        }

        if ( [] !== $fields ) {
            $payload['fields'] = $fields;
        }

        $response = $this->client->post( 'subscribers', $payload );

        return Subscriber::fromArray( $this->extractSubscriber( $response ) );
    }

    /**
     * Find a subscriber by their Kit ID.
     */
    public function find( int $id ): Subscriber
    {
        $response = $this->client->get( "subscribers/{$id}" );

        return Subscriber::fromArray( $this->extractSubscriber( $response ) );
    }

    /**
     * Find a subscriber by email address. Returns null if no match.
     */
    public function findByEmail( string $email ): ?Subscriber
    {
        $response = $this->client->get( 'subscribers', [ 'email_address' => $email ] );

        $rawList = is_array( $response['subscribers'] ?? null ) ? $response['subscribers'] : [];

        if ( [] === $rawList ) {
            return null;
        }

        return Subscriber::fromArray( $rawList[0] );
    }

    /**
     * Update a subscriber.
     *
     * @param  array<string, mixed>  $attributes  Any of `email_address`, `first_name`, `fields`.
     */
    public function update( int $id, array $attributes ): Subscriber
    {
        $response = $this->client->put( "subscribers/{$id}", $attributes );

        return Subscriber::fromArray( $this->extractSubscriber( $response ) );
    }

    /**
     * Apply a tag to a subscriber.
     */
    public function tag( int $subscriberId, int $tagId ): void
    {
        $this->client->post( "tags/{$tagId}/subscribers/{$subscriberId}" );
    }

    /**
     * Remove a tag from a subscriber.
     */
    public function untag( int $subscriberId, int $tagId ): void
    {
        $this->client->delete( "tags/{$tagId}/subscribers/{$subscriberId}" );
    }

    /**
     * Unsubscribe a subscriber from all future emails.
     *
     * A missing subscriber surfaces as `KitNotFoundException` from the Client's
     * 404 mapping — no defensive branch needed here.
     */
    public function unsubscribe( int $id ): Subscriber
    {
        $response = $this->client->post( "subscribers/{$id}/unsubscribe" );

        return Subscriber::fromArray( $this->extractSubscriber( $response ) );
    }

    /**
     * Build a paginated list of subscribers from a Kit response.
     *
     * Kept public so callers who invoke a raw `list` from higher-level code
     * can reconstruct a typed collection.
     *
     * @param  array<string, mixed>  $response
     *
     * @return PaginatedCollection<Subscriber>
     */
    public function toCollection( array $response ): PaginatedCollection
    {
        return PaginatedCollection::fromResponse(
            $response,
            'subscribers',
            static fn ( array $item ): Subscriber => Subscriber::fromArray( $item ),
        );
    }

    /**
     * Pull the `subscriber` object out of either a single-item or list response.
     *
     * @param  array<string, mixed>  $response
     *
     * @return array<string, mixed>
     */
    protected function extractSubscriber( array $response ): array
    {
        if ( is_array( $response['subscriber'] ?? null ) ) {
            return $response['subscriber'];
        }

        return $response;
    }
}
