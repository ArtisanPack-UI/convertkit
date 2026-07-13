<?php

/**
 * Kit form DTO.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Api\DTOs;

/**
 * Immutable representation of a Kit form.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
final class Form
{
    /**
     * @param  int  $id  Kit form ID.
     * @param  string  $name  Form name.
     * @param  string|null  $type  Form type (e.g. `embed`, `hosted`).
     * @param  string|null  $format  Form format (e.g. `inline`, `modal`, `slide in`, `sticky bar`).
     * @param  string|null  $createdAt  ISO 8601 creation timestamp.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $type = null,
        public readonly ?string $format = null,
        public readonly ?string $createdAt = null,
    ) {
    }

    /**
     * Build a Form from a Kit API payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        return new self(
            id        : (int) ( $data['id'] ?? 0 ),
            name      : (string) ( $data['name'] ?? '' ),
            type      : isset( $data['type'] ) ? (string) $data['type'] : null,
            format    : isset( $data['format'] ) ? (string) $data['format'] : null,
            createdAt : isset( $data['created_at'] ) ? (string) $data['created_at'] : null,
        );
    }
}
