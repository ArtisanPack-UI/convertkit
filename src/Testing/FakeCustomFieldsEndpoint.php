<?php

/**
 * Recording CustomFieldsEndpoint used by FakeConvertKit.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Testing;

use ArtisanPackUI\ConvertKit\Api\Endpoints\CustomFieldsEndpoint;

/**
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class FakeCustomFieldsEndpoint extends CustomFieldsEndpoint
{
    public function __construct( protected FakeConvertKit $fake )
    {
        // Deliberately skip parent constructor.
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
}
