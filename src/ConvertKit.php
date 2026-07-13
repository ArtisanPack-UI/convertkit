<?php

/**
 * Main ConvertKit class.
 *
 * This is the main class for the ConvertKit package, accessed via the
 * `convertkit()` helper function or the ConvertKit facade.
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

/**
 * Fluent entry point for the Kit v4 API.
 *
 * Resolves the four endpoint classes lazily on first access and hands out
 * cached singletons for the rest of the request lifecycle.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class ConvertKit
{
    protected ?SubscribersEndpoint $subscribers = null;

    protected ?FormsEndpoint $forms = null;

    protected ?TagsEndpoint $tags = null;

    protected ?CustomFieldsEndpoint $customFields = null;

    public function __construct( protected EndpointFactory $factory )
    {
    }

    /**
     * Get the low-level HTTP client.
     */
    public function client(): Client
    {
        return $this->factory->client();
    }

    /**
     * Subscribers endpoint.
     */
    public function subscribers(): SubscribersEndpoint
    {
        return $this->subscribers ??= $this->factory->subscribers();
    }

    /**
     * Forms endpoint.
     */
    public function forms(): FormsEndpoint
    {
        return $this->forms ??= $this->factory->forms();
    }

    /**
     * Tags endpoint.
     */
    public function tags(): TagsEndpoint
    {
        return $this->tags ??= $this->factory->tags();
    }

    /**
     * Custom fields endpoint.
     */
    public function customFields(): CustomFieldsEndpoint
    {
        return $this->customFields ??= $this->factory->customFields();
    }
}
