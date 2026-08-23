<?php

/**
 * Kit v4 account / stats endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Api\Endpoints;

use ArtisanPackUI\ConvertKit\Api\Client;
use ArtisanPackUI\ConvertKit\Api\DTOs\GrowthStats;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use InvalidArgumentException;

/**
 * Account stats endpoint with cached growth reads.
 *
 * Wraps Kit v4's `account/growth_stats` endpoint, which returns a single
 * aggregate ( subscriber count, new/cancelled/net movement ) for a window and
 * defaults to the last 90 days. Kit has no native time-series endpoint, so
 * `growthSeries()` composes one by requesting per-bucket aggregates across the
 * range. Results are cached like the reference-data endpoints, with a
 * configurable TTL, keyed per period so distinct windows don't collide.
 * `refresh()` and `refreshSeries()` force a re-fetch of a cached aggregate or
 * series respectively.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.2.0
 */
class AccountEndpoint
{

    /**
     * Hard ceiling on the number of buckets a single series may span. Because
     * each bucket triggers its own `growth_stats` request, a wide range paired
     * with a fine interval ( e.g. years at `day` granularity ) would otherwise
     * fan out into thousands of sequential API calls and hold as many DTOs in
     * memory. Ranges past this cap are rejected so a consumer that forwards
     * user-supplied dates cannot turn the call into a self-inflicted DoS.
     *
     * @var int
     */
    public const MAX_BUCKETS = 366;
    /**
     * @var array<int, string>
     */
    protected const INTERVALS = [ 'day', 'week', 'month' ];

    public function __construct(
        protected Client $client,
        protected CacheRepository $cache,
        protected string $cacheKey,
        protected int $ttl,
    ) {
    }

    /**
     * Growth stats for a period, hitting the cache first.
     *
     * Both bounds are optional; when omitted Kit defaults to the last 90 days.
     * Dates use the `yyyy-mm-dd` format and are interpreted in the account's
     * sending time zone.
     */
    public function stats( ?string $starting = null, ?string $ending = null ): GrowthStats
    {
        $this->assertValidWindow( $starting, $ending );

        return $this->cache->remember(
            $this->statsKey( $starting, $ending ),
            $this->ttl,
            fn (): GrowthStats => $this->fetchStats( $starting, $ending ),
        );
    }

    /**
     * A growth time series across a range, one point per interval bucket.
     *
     * Composed from per-bucket `account/growth_stats` calls, so a wide range
     * with a fine interval issues many requests — the whole series is cached
     * under one key to amortize that, and the range is capped at
     * `MAX_BUCKETS` buckets. Interval is one of `day`, `week`, or `month`:
     * `week` buckets are rolling 7-day windows anchored to `$starting`, while
     * `month` buckets are calendar-aligned ( so the first and last buckets of
     * a mid-month range are partial ).
     *
     * @return array<int, GrowthStats>
     */
    public function growthSeries( string $starting, string $ending, string $interval = 'week' ): array
    {
        $buckets = $this->buckets( $starting, $ending, $interval );

        return $this->cache->remember(
            $this->seriesKey( $starting, $ending, $interval ),
            $this->ttl,
            fn (): array => array_map(
                fn ( array $bucket ): GrowthStats => $this->fetchStats( $bucket[0], $bucket[1] ),
                $buckets,
            ),
        );
    }

    /**
     * Force a re-fetch of a period's stats and refresh the cache.
     */
    public function refresh( ?string $starting = null, ?string $ending = null ): GrowthStats
    {
        $this->assertValidWindow( $starting, $ending );

        $stats = $this->fetchStats( $starting, $ending );
        $this->cache->put( $this->statsKey( $starting, $ending ), $stats, $this->ttl );

        return $stats;
    }

    /**
     * Force a re-fetch of a growth time series and refresh its cache entry.
     *
     * The series counterpart to `refresh()`: it re-issues the per-bucket
     * `account/growth_stats` calls, ignoring any cached series, and stores the
     * fresh result under the same key `growthSeries()` reads from. Range and
     * interval are validated identically ( via `buckets()` ).
     *
     * @return array<int, GrowthStats>
     */
    public function refreshSeries( string $starting, string $ending, string $interval = 'week' ): array
    {
        $series = array_map(
            fn ( array $bucket ): GrowthStats => $this->fetchStats( $bucket[0], $bucket[1] ),
            $this->buckets( $starting, $ending, $interval ),
        );

        $this->cache->put( $this->seriesKey( $starting, $ending, $interval ), $series, $this->ttl );

        return $series;
    }

    protected function fetchStats( ?string $starting = null, ?string $ending = null ): GrowthStats
    {
        $query = [];

        if ( null !== $starting ) {
            $query['starting'] = $starting;
        }

        if ( null !== $ending ) {
            $query['ending'] = $ending;
        }

        $response = $this->client->get( 'account/growth_stats', $query );

        $raw = $response['stats'] ?? $response['growth_stats'] ?? $response;

        return GrowthStats::fromArray( is_array( $raw ) ? $raw : [] );
    }

    /**
     * Split a date range into `[ startingDate, endingDate ]` buckets.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function buckets( string $starting, string $ending, string $interval ): array
    {
        if ( ! in_array( $interval, self::INTERVALS, true ) ) {
            throw new InvalidArgumentException(
                "Unsupported interval '{$interval}'. Use one of: " . implode( ', ', self::INTERVALS ) . '.',
            );
        }

        $start = $this->parseDate( $starting, 'starting' )->startOfDay();
        $end   = $this->parseDate( $ending, 'ending' )->endOfDay();

        if ( $end < $start ) {
            throw new InvalidArgumentException( 'The ending date must not be before the starting date.' );
        }

        $buckets = [];
        $cursor  = $start;

        while ( $cursor <= $end ) {
            if ( count( $buckets ) >= self::MAX_BUCKETS ) {
                throw new InvalidArgumentException( sprintf(
                    'The requested range spans more than %d %s bucket(s). Narrow the range or use a coarser interval.',
                    self::MAX_BUCKETS,
                    $interval,
                ) );
            }

            $bucketEnd = match ( $interval ) {
                'day'   => $cursor->endOfDay(),
                'week'  => $cursor->addDays( 6 )->endOfDay(),
                'month' => $cursor->endOfMonth(),
                default => throw new InvalidArgumentException( "Unsupported interval '{$interval}'." ),
            };

            if ( $bucketEnd > $end ) {
                $bucketEnd = $end;
            }

            $buckets[] = [ $cursor->format( 'Y-m-d' ), $bucketEnd->format( 'Y-m-d' ) ];

            $cursor = $bucketEnd->addDay()->startOfDay();
        }

        return $buckets;
    }

    /**
     * Guard an optional window. `stats()` and `refresh()` forward their bounds
     * straight to Kit, so without this a malformed date would be silently sent
     * to the API. Each present bound is strictly parsed, and a fully-specified
     * window is rejected when it is inverted — the same contract `buckets()`
     * enforces for `growthSeries()`, so both entry points reject the same input.
     */
    protected function assertValidWindow( ?string $starting, ?string $ending ): void
    {
        $start = null === $starting ? null : $this->parseDate( $starting, 'starting' );
        $end   = null === $ending ? null : $this->parseDate( $ending, 'ending' );

        if ( null !== $start && null !== $end && $end < $start ) {
            throw new InvalidArgumentException( 'The ending date must not be before the starting date.' );
        }
    }

    /**
     * Strictly parse a canonical `yyyy-mm-dd` date, rejecting anything Kit
     * would not accept. Carbon's forgiving parser silently normalizes
     * noncanonical (`2026-1-1`) and overflowed (`2026-13-40`) input, so the
     * parsed value is re-formatted and compared byte-for-byte against the
     * original to reject those before they reach the cache key or the API.
     */
    protected function parseDate( string $date, string $bound ): CarbonImmutable
    {
        try {
            $parsed = CarbonImmutable::createFromFormat( '!Y-m-d', $date );
        } catch ( Exception $e ) {
            throw new InvalidArgumentException(
                "The {$bound} date '{$date}' is not a valid yyyy-mm-dd date.",
                0,
                $e,
            );
        }

        if ( ! $parsed instanceof CarbonImmutable || $parsed->format( 'Y-m-d' ) !== $date ) {
            throw new InvalidArgumentException(
                "The {$bound} date '{$date}' is not a valid yyyy-mm-dd date.",
            );
        }

        return $parsed;
    }

    protected function statsKey( ?string $starting, ?string $ending ): string
    {
        return $this->cacheKey . ':' . ( $starting ?? 'default' ) . ':' . ( $ending ?? 'default' );
    }

    protected function seriesKey( string $starting, string $ending, string $interval ): string
    {
        return "{$this->cacheKey}:series:{$starting}:{$ending}:{$interval}";
    }
}
