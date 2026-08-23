<?php

/**
 * Kit v4 broadcasts endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Api\Endpoints;

use ArtisanPackUI\ConvertKit\Api\Client;
use ArtisanPackUI\ConvertKit\Api\DTOs\Broadcast;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use InvalidArgumentException;

/**
 * Read-only broadcasts endpoint with a cached `list()` call.
 *
 * Wraps Kit v4's `broadcasts/stats` endpoint, which returns recent broadcasts
 * paired with their delivery/engagement stats ( recipients, open/click rates,
 * unsubscribes ) in a single cursor-paginated request — so the recent-broadcasts
 * widget gets everything it needs without a per-broadcast stats fan-out. Kit
 * returns broadcasts newest-first, so `list( $limit )` maps to "the $limit most
 * recent broadcasts". Results are cached like the reference-data endpoints, with
 * a configurable TTL, keyed per limit so distinct page sizes don't collide.
 *
 * The endpoint is intentionally read-only: create/update/delete are out of scope.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.2.0
 */
class BroadcastsEndpoint
{
    /**
     * Number of recent broadcasts returned when no limit is supplied.
     *
     * @var int
     */
    public const DEFAULT_LIMIT = 10;

    /**
     * Hard ceiling on how many broadcasts a single read may request. Kit
     * allows `per_page` up to 1000 ( default 500 ), but a dashboard widget has
     * no use for that many, and letting a consumer forward an unbounded
     * user-supplied page size would balloon the cached payload. Requests past
     * this are rejected.
     *
     * @var int
     */
    public const MAX_LIMIT = 100;

    public function __construct(
        protected Client $client,
        protected CacheRepository $cache,
        protected string $cacheKey,
        protected int $ttl,
    ) {
    }

    /**
     * List the most recent broadcasts with their stats, hitting the cache first.
     *
     * @return array<int, Broadcast>
     */
    public function list( int $limit = self::DEFAULT_LIMIT ): array
    {
        $limit = $this->normalizeLimit( $limit );

        return $this->cache->remember(
            $this->listKey( $limit ),
            $this->ttl,
            fn (): array => $this->fetchAll( $limit ),
        );
    }

    /**
     * Force a re-fetch and refresh the cache.
     *
     * @return array<int, Broadcast>
     */
    public function refresh( int $limit = self::DEFAULT_LIMIT ): array
    {
        $limit      = $this->normalizeLimit( $limit );
        $broadcasts = $this->fetchAll( $limit );
        $this->cache->put( $this->listKey( $limit ), $broadcasts, $this->ttl );

        return $broadcasts;
    }

    /**
     * @return array<int, Broadcast>
     */
    protected function fetchAll( int $limit ): array
    {
        $response = $this->client->get( 'broadcasts/stats', [ 'per_page' => $limit ] );

        $rawList = is_array( $response['broadcasts'] ?? null ) ? $response['broadcasts'] : [];

        return array_values( array_map(
            static fn ( array $item ): Broadcast => Broadcast::fromArray( $item ),
            $rawList,
        ) );
    }

    protected function normalizeLimit( int $limit ): int
    {
        if ( $limit < 1 || $limit > self::MAX_LIMIT ) {
            throw new InvalidArgumentException( sprintf(
                'The broadcasts limit must be between 1 and %d, got %d.',
                self::MAX_LIMIT,
                $limit,
            ) );
        }

        return $limit;
    }

    protected function listKey( int $limit ): string
    {
        return "{$this->cacheKey}:{$limit}";
    }
}
