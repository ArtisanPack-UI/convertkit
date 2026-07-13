<?php

/**
 * Kit API server error.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Api\Exceptions;

/**
 * Thrown when the Kit API returns HTTP 5xx (or an underlying network error
 * that the client couldn't recover from through retries).
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class KitServerException extends KitException
{
}
