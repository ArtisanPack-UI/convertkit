<?php

/**
 * Authentication failed against the Kit API.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Api\Exceptions;

/**
 * Thrown when the Kit API rejects the API key (HTTP 401 or 403).
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class KitAuthException extends KitException
{
}
