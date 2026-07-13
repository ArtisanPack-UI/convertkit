<?php

/**
 * Kit subscriber DTO.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Api\DTOs;

use ArtisanPackUI\ConvertKit\Api\Exceptions\KitException;

/**
 * Immutable representation of a Kit subscriber.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
final class Subscriber
{
    /**
     * @param  int  $id  Kit subscriber ID.
     * @param  string  $email  Email address.
     * @param  string  $state  Subscriber state (e.g. `active`, `cancelled`, `bounced`).
     * @param  string|null  $firstName  First name, if provided.
     * @param  string|null  $createdAt  ISO 8601 creation timestamp.
     * @param  array<string, mixed>  $fields  Custom field values keyed by field name.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $state,
        public readonly ?string $firstName = null,
        public readonly ?string $createdAt = null,
        public readonly array $fields = [],
    ) {
    }

    /**
     * Build a Subscriber from a Kit API payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        if ( ! isset( $data['id'] ) || ! is_numeric( $data['id'] ) ) {
            throw new KitException( 'Kit response is missing the required `id` field for a subscriber.', 0 );
        }

        return new self(
            id        : (int) $data['id'],
            email     : (string) ( $data['email_address'] ?? $data['email'] ?? '' ),
            state     : (string) ( $data['state'] ?? '' ),
            firstName : isset( $data['first_name'] ) ? (string) $data['first_name'] : null,
            createdAt : isset( $data['created_at'] ) ? (string) $data['created_at'] : null,
            fields    : is_array( $data['fields'] ?? null ) ? $data['fields'] : [],
        );
    }
}
