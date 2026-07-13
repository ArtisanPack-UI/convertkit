<?php

/**
 * Fired when a Kit subscribe call succeeds.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Events;

use ArtisanPackUI\ConvertKit\Api\DTOs\Subscriber;
use ArtisanPackUI\ConvertKit\Models\KitFeed;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class KitSubscribed
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly KitFeed $feed,
        public readonly Subscriber $subscriber,
        public readonly array $payload,
        public readonly int $submissionId,
    ) {
    }
}
