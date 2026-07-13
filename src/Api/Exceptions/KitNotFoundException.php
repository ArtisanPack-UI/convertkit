<?php

/**
 * Kit API resource not found.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Api\Exceptions;

/**
 * Thrown when a Kit API endpoint returns HTTP 404.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class KitNotFoundException extends KitException
{
}
