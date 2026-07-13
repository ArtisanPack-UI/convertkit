<?php

/**
 * Fired when a Kit subscribe call fails after all retries.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Events;

use ArtisanPackUI\ConvertKit\Models\KitFeed;
use Illuminate\Foundation\Events\Dispatchable;
use Throwable;

/**
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class KitSubscriptionFailed
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly KitFeed $feed,
        public readonly array $payload,
        public readonly int $submissionId,
        public readonly Throwable $exception,
    ) {
    }
}
