<?php

/**
 * Base exception for all Kit API errors.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Api\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base exception for all Kit API errors.
 *
 * Every exception raised by the Kit client extends this class. Callers can
 * catch `KitException` to handle any API error generically, or catch a more
 * specific subclass (auth, rate-limit, validation, not-found, server).
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class KitException extends RuntimeException
{
    /**
     * Raw response body decoded to an array, or null if not available.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $response;

    /**
     * @param  string  $message  Human-readable error message.
     * @param  int  $statusCode  HTTP status code from the response.
     * @param  array<string, mixed>|null  $response  Decoded response body.
     * @param  Throwable|null  $previous  Previous exception, if any.
     */
    public function __construct(
        string $message = '',
        int $statusCode = 0,
        ?array $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct( $message, $statusCode, $previous );
        $this->response = $response;
    }

    /**
     * Get the HTTP status code that produced this exception.
     */
    public function getStatusCode(): int
    {
        return $this->getCode();
    }

    /**
     * Get the decoded response body, if available.
     *
     * @return array<string, mixed>|null
     */
    public function getResponseBody(): ?array
    {
        return $this->response;
    }
}
