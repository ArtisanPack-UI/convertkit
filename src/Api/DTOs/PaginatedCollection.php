<?php

/**
 * Paginated collection of Kit API resources.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Api\DTOs;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Immutable, iterable, countable page of results from the Kit API.
 *
 * Kit v4 returns cursor-style pagination metadata under a `pagination` key.
 *
 * @template T
 *
 * @implements IteratorAggregate<int, T>
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
final class PaginatedCollection implements Countable, IteratorAggregate
{
    /**
     * @param  array<int, T>  $items  The items on the current page.
     * @param  bool  $hasPreviousPage  Whether there is a previous page.
     * @param  bool  $hasNextPage  Whether there is a next page.
     * @param  string|null  $startCursor  Cursor for the start of this page.
     * @param  string|null  $endCursor  Cursor for the end of this page (pass as `after` on the next request).
     * @param  int|null  $perPage  Page size the API used.
     */
    public function __construct(
        public readonly array $items,
        public readonly bool $hasPreviousPage = false,
        public readonly bool $hasNextPage = false,
        public readonly ?string $startCursor = null,
        public readonly ?string $endCursor = null,
        public readonly ?int $perPage = null,
    ) {
    }

    /**
     * Build a PaginatedCollection from a Kit API response payload.
     *
     * @param  array<string, mixed>  $payload  Full response body from Kit.
     * @param  string  $itemsKey  Top-level key that holds the array of items (e.g. `subscribers`, `forms`).
     * @param  callable  $mapper  Callable that maps a raw item array to a typed DTO instance.
     *
     * @return self<T>
     */
    public static function fromResponse( array $payload, string $itemsKey, callable $mapper ): self
    {
        $rawItems = is_array( $payload[ $itemsKey ] ?? null ) ? $payload[ $itemsKey ] : [];
        $items    = array_values( array_map( $mapper, $rawItems ) );
        $meta     = is_array( $payload['pagination'] ?? null ) ? $payload['pagination'] : [];

        return new self(
            items           : $items,
            hasPreviousPage : (bool) ( $meta['has_previous_page'] ?? false ),
            hasNextPage     : (bool) ( $meta['has_next_page'] ?? false ),
            startCursor     : isset( $meta['start_cursor'] ) ? (string) $meta['start_cursor'] : null,
            endCursor       : isset( $meta['end_cursor'] ) ? (string) $meta['end_cursor'] : null,
            perPage         : isset( $meta['per_page'] ) ? (int) $meta['per_page'] : null,
        );
    }

    public function count(): int
    {
        return count( $this->items );
    }

    /**
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator( $this->items );
    }
}
