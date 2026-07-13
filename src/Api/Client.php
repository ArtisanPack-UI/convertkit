<?php

/**
 * Kit v4 HTTP client.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Api;

use ArtisanPackUI\ConvertKit\Api\Exceptions\KitAuthException;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitException;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitNotFoundException;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitRateLimitException;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitServerException;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitValidationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Thin wrapper around Laravel's HTTP client that talks to the Kit v4 API.
 *
 * Responsibilities:
 * - Attach the auth header and JSON headers to every request.
 * - Retry on HTTP 429 and 5xx with exponential backoff. Respects `Retry-After`.
 * - Translate every non-2xx response and every transport error into a typed
 *   `KitException` subclass. Raw Guzzle/HTTP-facade exceptions never leak out.
 *
 * Endpoint classes are built on top of this by calling `get()`, `post()`,
 * `put()`, and `delete()`.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class Client
{
    public function __construct(
        protected HttpFactory $http,
        protected ?string $apiKey,
        protected string $baseUrl,
        protected int $timeout = 15,
        protected int $retries = 3,
        protected int $retryDelayMs = 500,
        protected int $maxBackoffMs = 30_000,
        protected int $maxRetryAfterSeconds = 60,
        protected bool $allowInsecureHttp = false,
    ) {
        if ( ! $this->allowInsecureHttp && ! str_starts_with( strtolower( $this->baseUrl ), 'https://' ) ) {
            throw new KitException(
                'Kit API base URL must use https://. Refusing to send the API key over cleartext HTTP. '
                . 'Set convertkit.allow_insecure_http to true to override (not recommended).',
                0,
            );
        }
    }

    /**
     * Perform a GET request.
     *
     * @param  array<string, mixed>  $query
     *
     * @return array<string, mixed>
     */
    public function get( string $path, array $query = [] ): array
    {
        return $this->request( 'GET', $path, [ 'query' => $query ] );
    }

    /**
     * Perform a POST request.
     *
     * @param  array<string, mixed>  $payload
     *
     * @return array<string, mixed>
     */
    public function post( string $path, array $payload = [] ): array
    {
        return $this->request( 'POST', $path, [ 'json' => $payload ] );
    }

    /**
     * Perform a PUT request.
     *
     * @param  array<string, mixed>  $payload
     *
     * @return array<string, mixed>
     */
    public function put( string $path, array $payload = [] ): array
    {
        return $this->request( 'PUT', $path, [ 'json' => $payload ] );
    }

    /**
     * Perform a DELETE request.
     *
     * @param  array<string, mixed>  $payload
     *
     * @return array<string, mixed>
     */
    public function delete( string $path, array $payload = [] ): array
    {
        return $this->request( 'DELETE', $path, [ 'json' => $payload ] );
    }

    /**
     * Send a request, retrying on transient failures.
     *
     * @param  array{query?: array<string, mixed>, json?: array<string, mixed>}  $options
     *
     * @return array<string, mixed>
     */
    protected function request( string $method, string $path, array $options ): array
    {
        if ( null === $this->apiKey || '' === $this->apiKey ) {
            throw new KitAuthException(
                'Kit API key is not configured. Set CONVERTKIT_API_KEY in your environment.',
                0,
            );
        }

        $url     = rtrim( $this->baseUrl, '/' ) . '/' . ltrim( $path, '/' );
        $attempt = 0;

        while ( true ) {
            $attempt++;

            try {
                $pending = $this->http
                    ->withHeaders( [
                        'Accept'        => 'application/json',
                        'Content-Type'  => 'application/json',
                        'X-Kit-Api-Key' => $this->apiKey,
                    ] )
                    ->timeout( $this->timeout );

                $response = match ( strtoupper( $method ) ) {
                    'GET'    => $pending->get( $url, $options['query'] ?? [] ),
                    'POST'   => $pending->post( $url, $options['json'] ?? [] ),
                    'PUT'    => $pending->put( $url, $options['json'] ?? [] ),
                    'DELETE' => $pending->delete( $url, $options['json'] ?? [] ),
                    default  => throw new KitException( "Unsupported HTTP method: {$method}", 0 ),
                };
            } catch ( ConnectionException $e ) {
                if ( $attempt <= $this->retries ) {
                    usleep( $this->backoffMicroseconds( $attempt ) );
                    continue;
                }

                throw new KitServerException(
                    'Failed to reach the Kit API: ' . $e->getMessage(),
                    0,
                    null,
                    $e,
                );
            } catch ( KitException $e ) {
                throw $e;
            } catch ( Throwable $e ) {
                throw new KitServerException(
                    'Unexpected error talking to the Kit API: ' . $e->getMessage(),
                    0,
                    null,
                    $e,
                );
            }

            if ( $response->successful() ) {
                return $this->decode( $response );
            }

            if ( $this->isRetryable( $response->status() ) && $attempt <= $this->retries ) {
                $sleepUs = $this->delayFor( $response, $attempt );

                // If the server-provided Retry-After exceeds our cap, don't wait — surface
                // the exception so the caller can decide (queue backoff, alert, etc.).
                if ( null === $sleepUs ) {
                    throw $this->exceptionFor( $response );
                }

                usleep( $sleepUs );
                continue;
            }

            throw $this->exceptionFor( $response );
        }
    }

    /**
     * Decode a successful response body to an associative array.
     *
     * @return array<string, mixed>
     */
    protected function decode( Response $response ): array
    {
        $body = trim( $response->body() );

        if ( '' === $body ) {
            return [];
        }

        $decoded = json_decode( $body, true );

        if ( ! is_array( $decoded ) ) {
            throw new KitServerException(
                'Kit API returned a non-JSON body: ' . substr( $body, 0, 200 ),
                $response->status(),
            );
        }

        return $decoded;
    }

    /**
     * Map an HTTP response to the correct KitException subclass.
     */
    protected function exceptionFor( Response $response ): KitException
    {
        $status  = $response->status();
        $body    = $this->safeDecodeBody( $response );
        $message = is_string( $body['message'] ?? null ) && '' !== $body['message']
            ? $body['message']
            : ( $response->reason() ?: 'Kit API request failed.' );

        return match ( true ) {
            401 === $status || 403 === $status => new KitAuthException(
                $message,
                $status,
                $body,
            ),
            404 === $status => new KitNotFoundException(
                $message,
                $status,
                $body,
            ),
            429 === $status => new KitRateLimitException(
                $message,
                $status,
                $this->retryAfterSeconds( $response ),
                $body,
            ),
            $status >= 500 => new KitServerException(
                $message,
                $status,
                $body,
            ),
            $status >= 400 => new KitValidationException(
                $message,
                $status,
                $this->extractValidationErrors( $body ),
                $body,
            ),
            default => new KitException(
                $message,
                $status,
                $body,
            ),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function safeDecodeBody( Response $response ): ?array
    {
        try {
            $decoded = $response->json();

            return is_array( $decoded ) ? $decoded : null;
        } catch ( Throwable ) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $body
     *
     * @return array<int, string>
     */
    protected function extractValidationErrors( ?array $body ): array
    {
        if ( null === $body ) {
            return [];
        }

        $errors = $body['errors'] ?? null;

        if ( ! is_array( $errors ) ) {
            return [];
        }

        $flat = [];

        array_walk_recursive( $errors, function ( $value ) use ( &$flat ): void {
            if ( is_string( $value ) && '' !== $value ) {
                $flat[] = $value;
            }
        } );

        return $flat;
    }

    protected function isRetryable( int $status ): bool
    {
        return 429 === $status || $status >= 500;
    }

    protected function retryAfterSeconds( Response $response ): ?int
    {
        $header = $response->header( 'Retry-After' );

        if ( '' === $header ) {
            return null;
        }

        if ( ctype_digit( $header ) ) {
            return (int) $header;
        }

        $timestamp = strtotime( $header );

        if ( false === $timestamp ) {
            return null;
        }

        return max( 0, $timestamp - time() );
    }

    /**
     * Compute how long to wait before the next attempt (in microseconds), or
     * null if the server asked us to wait longer than we're willing to.
     *
     * Prefers a `Retry-After` header when present; otherwise uses exponential
     * backoff based on the configured base delay. Both branches are bounded
     * so a misbehaving 429 (or a big retry count) can't pin the process for
     * minutes.
     */
    protected function delayFor( Response $response, int $attempt ): ?int
    {
        $retryAfter = $this->retryAfterSeconds( $response );

        if ( null !== $retryAfter ) {
            if ( $retryAfter > $this->maxRetryAfterSeconds ) {
                return null;
            }

            return max( 0, $retryAfter ) * 1_000_000;
        }

        return $this->backoffMicroseconds( $attempt );
    }

    protected function backoffMicroseconds( int $attempt ): int
    {
        $ms = $this->retryDelayMs * ( 2 ** ( $attempt - 1 ) );

        return min( $ms, $this->maxBackoffMs ) * 1000;
    }
}
