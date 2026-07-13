<?php

/**
 * Recording FormsEndpoint used by FakeConvertKit.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Testing;

use ArtisanPackUI\ConvertKit\Api\DTOs\Subscriber;
use ArtisanPackUI\ConvertKit\Api\Endpoints\FormsEndpoint;

/**
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class FakeFormsEndpoint extends FormsEndpoint
{
    public function __construct( protected FakeConvertKit $fake )
    {
        // Deliberately skip parent constructor — no Client / cache needed.
    }

    /**
     * @return array<int, mixed>
     */
    public function list(): array
    {
        return [];
    }

    /**
     * @return array<int, mixed>
     */
    public function refresh(): array
    {
        return [];
    }

    public function subscribe(
        int $formId,
        string $email,
        array $fields = [],
        array $tags = [],
    ): Subscriber {
        return $this->fake->recordSubscribe( $email, null, $fields, $formId, $tags );
    }
}
