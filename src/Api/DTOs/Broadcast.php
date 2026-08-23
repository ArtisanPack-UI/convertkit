<?php

/**
 * Kit broadcast DTO.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Api\DTOs;

/**
 * Immutable representation of a Kit broadcast, paired with its engagement stats.
 *
 * Built from the `account`-wide `broadcasts/stats` endpoint, which returns each
 * broadcast's `id`, `subject`, and `send_at` alongside a nested `stats` object.
 * `sendAt` is null for drafts that have not been scheduled.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.2.0
 */
final class Broadcast
{
    /**
     * @param  int  $id  Kit broadcast ID.
     * @param  string  $subject  Broadcast subject line.
     * @param  BroadcastStats  $stats  Delivery and engagement stats.
     * @param  string|null  $sendAt  ISO 8601 scheduled/sent timestamp, or null for an unscheduled draft.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $subject,
        public readonly BroadcastStats $stats,
        public readonly ?string $sendAt = null,
    ) {
    }

    /**
     * Build a Broadcast from a Kit `broadcasts/stats` list item.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        $stats = is_array( $data['stats'] ?? null ) ? $data['stats'] : [];

        return new self(
            id      : (int) ( $data['id'] ?? 0 ),
            subject : (string) ( $data['subject'] ?? '' ),
            stats   : BroadcastStats::fromArray( $stats ),
            sendAt  : isset( $data['send_at'] ) ? (string) $data['send_at'] : null,
        );
    }
}
