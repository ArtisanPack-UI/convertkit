<?php

/**
 * ConvertKit Facade.
 *
 * Provides static access to the ConvertKit class.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * ConvertKit Facade.
 *
 * @see \ArtisanPackUI\ConvertKit\ConvertKit
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class ConvertKit extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @since 1.0.0
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'convertkit';
    }
}
