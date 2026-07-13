<?php

/**
 * Factory that builds Kit endpoint instances from the container.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit;

use ArtisanPackUI\ConvertKit\Api\Client;
use ArtisanPackUI\ConvertKit\Api\Endpoints\CustomFieldsEndpoint;
use ArtisanPackUI\ConvertKit\Api\Endpoints\FormsEndpoint;
use ArtisanPackUI\ConvertKit\Api\Endpoints\SubscribersEndpoint;
use ArtisanPackUI\ConvertKit\Api\Endpoints\TagsEndpoint;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Central place that resolves the Client + reference-data endpoints.
 *
 * Endpoints depend on cache store + TTL config, so we build them here
 * rather than binding each individually in the service provider.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class EndpointFactory
{
    public function __construct(
        protected Client $client,
        protected CacheFactory $cacheFactory,
        protected ConfigRepository $config,
    ) {
    }

    public function client(): Client
    {
        return $this->client;
    }

    public function subscribers(): SubscribersEndpoint
    {
        return new SubscribersEndpoint( $this->client );
    }

    public function forms(): FormsEndpoint
    {
        return new FormsEndpoint(
            $this->client,
            $this->cache(),
            $this->cacheKey( 'forms' ),
            (int) $this->config->get( 'convertkit.cache.forms_ttl', 3600 ),
        );
    }

    public function tags(): TagsEndpoint
    {
        return new TagsEndpoint(
            $this->client,
            $this->cache(),
            $this->cacheKey( 'tags' ),
            (int) $this->config->get( 'convertkit.cache.tags_ttl', 3600 ),
        );
    }

    public function customFields(): CustomFieldsEndpoint
    {
        return new CustomFieldsEndpoint(
            $this->client,
            $this->cache(),
            $this->cacheKey( 'fields' ),
            (int) $this->config->get( 'convertkit.cache.fields_ttl', 3600 ),
        );
    }

    protected function cache(): \Illuminate\Contracts\Cache\Repository
    {
        $store = $this->config->get( 'convertkit.cache.store' );

        return $this->cacheFactory->store( is_string( $store ) && '' !== $store ? $store : null );
    }

    protected function cacheKey( string $suffix ): string
    {
        $prefix = (string) $this->config->get( 'convertkit.cache.prefix', 'convertkit' );

        // Bind the cache key to the API key so rotating credentials (or sharing
        // a cache store across environments/accounts) can't serve stale data
        // from a different Kit account.
        $apiKey  = (string) ( $this->config->get( 'convertkit.api_key' ) ?? '' );
        $account = '' === $apiKey ? 'anon' : substr( hash( 'sha256', $apiKey ), 0, 12 );

        return "{$prefix}:{$account}:{$suffix}";
    }
}
