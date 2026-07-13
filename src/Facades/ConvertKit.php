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

use ArtisanPackUI\ConvertKit\ConvertKit as ConvertKitService;
use ArtisanPackUI\ConvertKit\Testing\FakeConvertKit;
use Illuminate\Support\Facades\Facade;

/**
 * ConvertKit Facade.
 *
 * @see ConvertKitService
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class ConvertKit extends Facade
{
    /**
     * Swap the real ConvertKit binding for a recording fake and return it
     * so tests can drive assertions on the returned instance.
     *
     * @since 1.0.0
     */
    public static function fake(): FakeConvertKit
    {
        $fake = new FakeConvertKit();

        static::swap( $fake );

        // Also bind against the concrete class so consumers who inject
        // ConvertKit via constructor / method type-hint (e.g. queued
        // jobs, controllers) resolve the fake too.
        static::$app->instance( ConvertKitService::class, $fake );

        return $fake;
    }

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
