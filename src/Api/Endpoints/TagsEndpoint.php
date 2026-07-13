<?php

/**
 * Kit v4 tags endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Api\Endpoints;

use ArtisanPackUI\ConvertKit\Api\Client;
use ArtisanPackUI\ConvertKit\Api\DTOs\Tag;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Tags endpoint with a cached `list()` call.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class TagsEndpoint
{
    public function __construct(
        protected Client $client,
        protected CacheRepository $cache,
        protected string $cacheKey,
        protected int $ttl,
    ) {
    }

    /**
     * List every tag, hitting the cache first.
     *
     * @return array<int, Tag>
     */
    public function list(): array
    {
        return $this->cache->remember( $this->cacheKey, $this->ttl, fn (): array => $this->fetchAll() );
    }

    /**
     * Force a re-fetch and refresh the cache.
     *
     * @return array<int, Tag>
     */
    public function refresh(): array
    {
        $tags = $this->fetchAll();
        $this->cache->put( $this->cacheKey, $tags, $this->ttl );

        return $tags;
    }

    /**
     * Create a new tag. Invalidates the cache.
     */
    public function create( string $name ): Tag
    {
        $response = $this->client->post( 'tags', [ 'name' => $name ] );

        $data = is_array( $response['tag'] ?? null ) ? $response['tag'] : $response;
        $this->cache->forget( $this->cacheKey );

        return Tag::fromArray( $data );
    }

    /**
     * Apply a tag to a subscriber (by subscriber ID).
     */
    public function apply( int $tagId, int $subscriberId ): void
    {
        $this->client->post( "tags/{$tagId}/subscribers/{$subscriberId}" );
    }

    /**
     * Remove a tag from a subscriber (by subscriber ID).
     */
    public function remove( int $tagId, int $subscriberId ): void
    {
        $this->client->delete( "tags/{$tagId}/subscribers/{$subscriberId}" );
    }

    /**
     * @return array<int, Tag>
     */
    protected function fetchAll(): array
    {
        $response = $this->client->get( 'tags' );

        $rawList = is_array( $response['tags'] ?? null ) ? $response['tags'] : [];

        return array_values( array_map(
            static fn ( array $item ): Tag => Tag::fromArray( $item ),
            $rawList,
        ) );
    }
}
