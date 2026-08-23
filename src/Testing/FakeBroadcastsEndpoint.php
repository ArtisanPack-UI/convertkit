<?php

/**
 * Recording BroadcastsEndpoint used by FakeConvertKit.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Testing;

use ArtisanPackUI\ConvertKit\Api\DTOs\Broadcast;
use ArtisanPackUI\ConvertKit\Api\Endpoints\BroadcastsEndpoint;

/**
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.2.0
 */
class FakeBroadcastsEndpoint extends BroadcastsEndpoint
{
    public function __construct( protected FakeConvertKit $fake )
    {
        // Deliberately skip parent constructor — no Client / cache needed.
    }

    /**
     * Mirror the real endpoint's contract: the same limit validation ( via
     * `normalizeLimit()` ) then the seeded broadcasts sliced to the limit, or an
     * empty list when nothing was seeded. Never touches the network.
     *
     * @return array<int, Broadcast>
     */
    public function list( int $limit = self::DEFAULT_LIMIT ): array
    {
        return $this->fake->resolveBroadcasts( $this->normalizeLimit( $limit ) );
    }

    /**
     * @return array<int, Broadcast>
     */
    public function refresh( int $limit = self::DEFAULT_LIMIT ): array
    {
        return $this->fake->resolveBroadcasts( $this->normalizeLimit( $limit ) );
    }
}
