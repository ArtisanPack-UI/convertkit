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
     * @return array<int, mixed>
     */
    public function list( int $limit = self::DEFAULT_LIMIT ): array
    {
        return [];
    }

    /**
     * @return array<int, mixed>
     */
    public function refresh( int $limit = self::DEFAULT_LIMIT ): array
    {
        return [];
    }
}
