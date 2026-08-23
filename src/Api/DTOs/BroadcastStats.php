<?php

/**
 * Kit broadcast engagement-stats DTO.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Api\DTOs;

/**
 * Immutable representation of the `stats` object Kit returns for a broadcast.
 *
 * Rates ( `open_rate`, `click_rate`, `unsubscribe_rate`, `progress` ) come back
 * as numbers, not percentages — a 50% open rate is `0.5`. Counts default to
 * zero and `status` to null so a draft broadcast ( which has no engagement yet )
 * still maps cleanly.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.2.0
 */
final class BroadcastStats
{
    /**
     * @param  int  $recipients  Number of subscribers the broadcast was sent to.
     * @param  int  $emailsOpened  Number of distinct opens.
     * @param  float  $openRate  Open rate as a fraction ( 0.5 === 50% ).
     * @param  int  $totalClicks  Total link clicks across the broadcast.
     * @param  float  $clickRate  Click rate as a fraction ( 0.5 === 50% ).
     * @param  int  $unsubscribes  Number of unsubscribes attributed to the broadcast.
     * @param  float  $unsubscribeRate  Unsubscribe rate as a fraction ( 0.5 === 50% ).
     * @param  float  $progress  Send progress as a fraction ( 1.0 === complete ).
     * @param  string|null  $status  Broadcast status ( e.g. `draft`, `scheduled`, `completed` ).
     */
    public function __construct(
        public readonly int $recipients,
        public readonly int $emailsOpened,
        public readonly float $openRate,
        public readonly int $totalClicks,
        public readonly float $clickRate,
        public readonly int $unsubscribes,
        public readonly float $unsubscribeRate,
        public readonly float $progress,
        public readonly ?string $status = null,
    ) {
    }

    /**
     * Build a BroadcastStats from a Kit API `stats` payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        return new self(
            recipients      : (int) ( $data['recipients'] ?? 0 ),
            emailsOpened    : (int) ( $data['emails_opened'] ?? 0 ),
            openRate        : (float) ( $data['open_rate'] ?? 0 ),
            totalClicks     : (int) ( $data['total_clicks'] ?? 0 ),
            clickRate       : (float) ( $data['click_rate'] ?? 0 ),
            unsubscribes    : (int) ( $data['unsubscribes'] ?? 0 ),
            unsubscribeRate : (float) ( $data['unsubscribe_rate'] ?? 0 ),
            progress        : (float) ( $data['progress'] ?? 0 ),
            status          : isset( $data['status'] ) ? (string) $data['status'] : null,
        );
    }
}
