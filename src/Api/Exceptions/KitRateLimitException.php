<?php

/**
 * Kit API rate limit exceeded.
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
 * Thrown when the Kit API returns HTTP 429.
 *
 * Carries the `Retry-After` header value (in seconds) so callers can
 * implement their own backoff if needed. The client itself will already
 * have retried up to the configured number of times.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class KitRateLimitException extends KitException
{
    /**
     * @param  string  $message  Human-readable error message.
     * @param  int  $statusCode  HTTP status code.
     * @param  int|null  $retryAfter  Seconds until it's safe to retry, if the API provided a `Retry-After` header.
     * @param  array<string, mixed>|null  $response  Decoded response body.
     * @param  Throwable|null  $previous  Previous exception, if any.
     */
    public function __construct(
        string $message = '',
        int $statusCode = 429,
        public readonly ?int $retryAfter = null,
        ?array $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct( $message, $statusCode, $response, $previous );
    }
}
