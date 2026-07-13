<?php

/**
 * Kit v4 forms endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Api\Endpoints;

use ArtisanPackUI\ConvertKit\Api\Client;
use ArtisanPackUI\ConvertKit\Api\DTOs\Form;
use ArtisanPackUI\ConvertKit\Api\DTOs\Subscriber;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Forms endpoint with a cached `list()` call.
 *
 * The list is cached under `{prefix}:forms` in the configured cache store,
 * with the TTL taken from `convertkit.cache.forms_ttl`. Use `refresh()` (or
 * the `convertkit:sync forms` command) to force a re-fetch.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class FormsEndpoint
{
    public function __construct(
        protected Client $client,
        protected CacheRepository $cache,
        protected string $cacheKey,
        protected int $ttl,
    ) {
    }

    /**
     * List every form, hitting the cache first.
     *
     * @return array<int, Form>
     */
    public function list(): array
    {
        return $this->cache->remember( $this->cacheKey, $this->ttl, fn (): array => $this->fetchAll() );
    }

    /**
     * Force a re-fetch and refresh the cache.
     *
     * @return array<int, Form>
     */
    public function refresh(): array
    {
        $forms = $this->fetchAll();
        $this->cache->put( $this->cacheKey, $forms, $this->ttl );

        return $forms;
    }

    /**
     * Subscribe an email to a specific form.
     *
     * @param  array<string, mixed>  $fields  Optional custom field values keyed by field key.
     * @param  array<int, int|string>  $tags  Optional tag IDs to also apply.
     */
    public function subscribe(
        int $formId,
        string $email,
        array $fields = [],
        array $tags = [],
    ): Subscriber {
        $payload = [ 'email_address' => $email ];

        if ( [] !== $fields ) {
            $payload['fields'] = $fields;
        }

        if ( [] !== $tags ) {
            $payload['tags'] = $tags;
        }

        $response = $this->client->post( "forms/{$formId}/subscribers", $payload );

        $subscriber = is_array( $response['subscriber'] ?? null ) ? $response['subscriber'] : $response;

        return Subscriber::fromArray( $subscriber );
    }

    /**
     * @return array<int, Form>
     */
    protected function fetchAll(): array
    {
        $response = $this->client->get( 'forms' );

        $rawList = is_array( $response['forms'] ?? null ) ? $response['forms'] : [];

        return array_values( array_map(
            static fn ( array $item ): Form => Form::fromArray( $item ),
            $rawList,
        ) );
    }
}
