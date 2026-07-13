<?php

/**
 * Kit tag DTO.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Api\DTOs;

/**
 * Immutable representation of a Kit tag.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
final class Tag
{
    /**
     * @param  int  $id  Kit tag ID.
     * @param  string  $name  Tag name.
     * @param  string|null  $createdAt  ISO 8601 creation timestamp.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $createdAt = null,
    ) {
    }

    /**
     * Build a Tag from a Kit API payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        return new self(
            id        : (int) ( $data['id'] ?? 0 ),
            name      : (string) ( $data['name'] ?? '' ),
            createdAt : isset( $data['created_at'] ) ? (string) $data['created_at'] : null,
        );
    }
}
