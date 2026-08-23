<?php

/**
 * Recording AccountEndpoint used by FakeConvertKit.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Testing;

use ArtisanPackUI\ConvertKit\Api\DTOs\GrowthStats;
use ArtisanPackUI\ConvertKit\Api\Endpoints\AccountEndpoint;

/**
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.2.0
 */
class FakeAccountEndpoint extends AccountEndpoint
{
    public function __construct( protected FakeConvertKit $fake )
    {
        // Deliberately skip parent constructor.
    }

    public function stats( ?string $starting = null, ?string $ending = null ): GrowthStats
    {
        return new GrowthStats( 0, 0, 0, 0, $starting, $ending );
    }

    /**
     * Mirror the real endpoint's contract: one zero-valued point per bucket,
     * with the same range/interval validation. Still network-free — `buckets()`
     * is pure date math, so no Kit request is ever made.
     *
     * @return array<int, GrowthStats>
     */
    public function growthSeries( string $starting, string $ending, string $interval = 'week' ): array
    {
        return array_map(
            static fn ( array $bucket ): GrowthStats => new GrowthStats( 0, 0, 0, 0, $bucket[0], $bucket[1] ),
            $this->buckets( $starting, $ending, $interval ),
        );
    }

    public function refresh( ?string $starting = null, ?string $ending = null ): GrowthStats
    {
        return new GrowthStats( 0, 0, 0, 0, $starting, $ending );
    }
}
