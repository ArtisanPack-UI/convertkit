<?php

/**
 * Exception thrown by FieldMapper for caller-catchable mapping failures.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Support;

use RuntimeException;

/**
 * Thrown when a submission cannot be mapped to a Kit payload — e.g. the
 * feed's `field_map` has no `email_address` entry, or the submission
 * carries no value for it.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class FieldMapperException extends RuntimeException
{
}
