<?php

/**
 * Kit account growth-stats DTO.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Api\DTOs;

/**
 * Immutable representation of a Kit `account/growth_stats` result.
 *
 * Kit returns a single aggregate per period: the subscriber count at the end
 * of the window plus the movement (new, cancelled, net) across it. Timestamps
 * come back in the account's sending time zone, not UTC.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.2.0
 */
final class GrowthStats
{
    /**
     * @param  int  $subscribers  Total subscriber count at the end of the period.
     * @param  int  $netNewSubscribers  Net growth over the period ( new minus cancellations ).
     * @param  int  $newSubscribers  Subscribers gained over the period.
     * @param  int  $cancellations  Subscribers lost over the period.
     * @param  string|null  $starting  ISO 8601 start of the period ( account time zone ).
     * @param  string|null  $ending  ISO 8601 end of the period ( account time zone ).
     */
    public function __construct(
        public readonly int $subscribers,
        public readonly int $netNewSubscribers,
        public readonly int $newSubscribers,
        public readonly int $cancellations,
        public readonly ?string $starting = null,
        public readonly ?string $ending = null,
    ) {
    }

    /**
     * Build a GrowthStats from a Kit API payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        return new self(
            subscribers       : (int) ( $data['subscribers'] ?? 0 ),
            netNewSubscribers : (int) ( $data['net_new_subscribers'] ?? 0 ),
            newSubscribers    : (int) ( $data['new_subscribers'] ?? 0 ),
            cancellations     : (int) ( $data['cancellations'] ?? 0 ),
            starting          : isset( $data['starting'] ) ? (string) $data['starting'] : null,
            ending            : isset( $data['ending'] ) ? (string) $data['ending'] : null,
        );
    }
}
