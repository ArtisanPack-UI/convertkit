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
        $this->assertValidWindow( $starting, $ending );

        return $this->fake->resolveStats( $starting, $ending );
    }

    /**
     * Mirror the real endpoint's contract: the same range/interval validation
     * ( via `buckets()` ) then the seeded series, or one zero-valued point per
     * bucket when nothing was seeded. Still network-free — `buckets()` is pure
     * date math, so no Kit request is ever made.
     *
     * @return array<int, GrowthStats>
     */
    public function growthSeries( string $starting, string $ending, string $interval = 'week' ): array
    {
        return $this->fake->resolveGrowthSeries(
            $starting,
            $ending,
            $interval,
            $this->buckets( $starting, $ending, $interval ),
        );
    }

    public function refresh( ?string $starting = null, ?string $ending = null ): GrowthStats
    {
        $this->assertValidWindow( $starting, $ending );

        return $this->fake->resolveStats( $starting, $ending );
    }

    /**
     * Mirror the real endpoint's `refreshSeries()`: the same range/interval
     * validation ( via `buckets()` ) then the seeded series, recorded like any
     * other series read. Still network-free.
     *
     * @return array<int, GrowthStats>
     */
    public function refreshSeries( string $starting, string $ending, string $interval = 'week' ): array
    {
        return $this->fake->resolveGrowthSeries(
            $starting,
            $ending,
            $interval,
            $this->buckets( $starting, $ending, $interval ),
        );
    }
}
