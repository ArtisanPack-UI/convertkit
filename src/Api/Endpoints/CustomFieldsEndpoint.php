<?php

/**
 * Kit v4 custom fields endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Api\Endpoints;

use ArtisanPackUI\ConvertKit\Api\Client;
use ArtisanPackUI\ConvertKit\Api\DTOs\CustomField;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Custom fields endpoint with a cached `list()` call.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class CustomFieldsEndpoint
{
    public function __construct(
        protected Client $client,
        protected CacheRepository $cache,
        protected string $cacheKey,
        protected int $ttl,
    ) {
    }

    /**
     * List every custom field, hitting the cache first.
     *
     * @return array<int, CustomField>
     */
    public function list(): array
    {
        return $this->cache->remember( $this->cacheKey, $this->ttl, fn (): array => $this->fetchAll() );
    }

    /**
     * Force a re-fetch and refresh the cache.
     *
     * @return array<int, CustomField>
     */
    public function refresh(): array
    {
        $fields = $this->fetchAll();
        $this->cache->put( $this->cacheKey, $fields, $this->ttl );

        return $fields;
    }

    /**
     * Create a new custom field. Invalidates the cache.
     */
    public function create( string $label ): CustomField
    {
        $response = $this->client->post( 'custom_fields', [ 'label' => $label ] );

        $data = is_array( $response['custom_field'] ?? null ) ? $response['custom_field'] : $response;
        $this->cache->forget( $this->cacheKey );

        return CustomField::fromArray( $data );
    }

    /**
     * @return array<int, CustomField>
     */
    protected function fetchAll(): array
    {
        $response = $this->client->get( 'custom_fields' );

        $rawList = is_array( $response['custom_fields'] ?? null ) ? $response['custom_fields'] : [];

        return array_values( array_map(
            static fn ( array $item ): CustomField => CustomField::fromArray( $item ),
            $rawList,
        ) );
    }
}
