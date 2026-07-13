<?php

/**
 * Kit custom field DTO.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Api\DTOs;

/**
 * Immutable representation of a Kit custom field.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
final class CustomField
{
    /**
     * @param  int  $id  Kit custom field ID.
     * @param  string  $label  Human-readable label.
     * @param  string  $key  Machine key used when writing values on a subscriber.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $label,
        public readonly string $key,
    ) {
    }

    /**
     * Build a CustomField from a Kit API payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        return new self(
            id    : (int) ( $data['id'] ?? 0 ),
            label : (string) ( $data['label'] ?? '' ),
            key   : (string) ( $data['key'] ?? '' ),
        );
    }
}
