<?php

/**
 * Fired when a Kit feed is skipped without hitting the network.
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

/**
 * A feed is skipped when its conditional logic evaluates false, or when its
 * field map cannot resolve an email address for the submission.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class KitFeedSkipped
{
    use Dispatchable;

    public function __construct(
        public readonly KitFeed $feed,
        public readonly int $submissionId,
        public readonly string $reason,
    ) {
    }
}
