<?php

/**
 * Kit API validation error.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Api\Exceptions;

use Throwable;

/**
 * Thrown when the Kit API rejects the request payload (HTTP 4xx other
 * than 401/403/404/429).
 *
 * The decoded response body typically contains an `errors` array describing
 * the offending fields.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class KitValidationException extends KitException
{
    /**
     * @param  string  $message  Human-readable error message.
     * @param  int  $statusCode  HTTP status code.
     * @param  array<int, string>  $errors  Flat list of validation error messages.
     * @param  array<string, mixed>|null  $response  Decoded response body.
     * @param  Throwable|null  $previous  Previous exception, if any.
     */
    public function __construct(
        string $message = '',
        int $statusCode = 422,
        public readonly array $errors = [],
        ?array $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct( $message, $statusCode, $response, $previous );
    }
}
