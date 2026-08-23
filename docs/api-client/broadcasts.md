---
title: Broadcasts
---

# Broadcasts

Wraps Kit v4's `broadcasts/stats` endpoint. Access via `ConvertKit::broadcasts()` or `convertkit()->broadcasts()`.

The endpoint is intentionally **read-only** — it surfaces recent broadcasts paired with their delivery and engagement stats for a dashboard widget. Create/update/delete are out of scope.

The `list()` call is cached — see [reference-data caching](API-Client#reference-data-caching).

## `list( int $limit = 10 ): array<int, Broadcast>`

Return the most recent broadcasts, each with its stats, in a single cursor-paginated request. Kit returns broadcasts newest-first, so `list( $limit )` maps to "the `$limit` most recent broadcasts".

```php
$broadcasts = ConvertKit::broadcasts()->list();     // 10 most recent
$broadcasts = ConvertKit::broadcasts()->list( 25 );

foreach ( $broadcasts as $broadcast ) {
    $broadcast->subject;             // "This week in ..."
    $broadcast->sendAt;              // ISO 8601, or null for an unscheduled draft
    $broadcast->stats->recipients;   // 1284
    $broadcast->stats->openRate;     // 0.42  (a fraction, not a percentage)
    $broadcast->stats->clickRate;    // 0.08
}
```

- `limit` defaults to `BroadcastsEndpoint::DEFAULT_LIMIT` (10) and is capped at `BroadcastsEndpoint::MAX_LIMIT` (100). Kit itself allows `per_page` up to 500, but a widget has no use for that many and an unbounded page size would balloon the cached payload — a limit below 1 or above 100 throws `InvalidArgumentException`.
- Cache key: `{prefix}:{account}:broadcasts:{limit}` (keyed per limit so distinct page sizes don't collide). TTL: `convertkit.cache.broadcasts_ttl` (default 1 hour, override with `CONVERTKIT_BROADCASTS_TTL`).

## `refresh( int $limit = 10 ): array<int, Broadcast>`

Force a re-fetch and refresh the cache for that limit.

```php
$broadcasts = ConvertKit::broadcasts()->refresh();
```

## Broadcast DTO

```php
final class Broadcast
{
    public function __construct(
        public readonly int $id,
        public readonly string $subject,
        public readonly BroadcastStats $stats,
        public readonly ?string $sendAt = null, // null for an unscheduled draft
    ) {}
}
```

## BroadcastStats DTO

Rates come back as fractions, not percentages — a 50% open rate is `0.5`. Counts default to zero and `status` to null, so a draft broadcast (no engagement yet) still maps cleanly.

```php
final class BroadcastStats
{
    public function __construct(
        public readonly int $recipients,
        public readonly int $emailsOpened,
        public readonly float $openRate,        // 0.5 === 50%
        public readonly int $totalClicks,
        public readonly float $clickRate,       // 0.5 === 50%
        public readonly int $unsubscribes,
        public readonly float $unsubscribeRate, // 0.5 === 50%
        public readonly float $progress,        // 1.0 === complete
        public readonly ?string $status = null, // e.g. draft, scheduled, completed
    ) {}
}
```

## Testing

Under [`ConvertKit::fake()`](Testing) the endpoint is network-free: seed broadcasts (newest-first) with `fakeBroadcasts()` and assert the read with `assertBroadcastsListed()`. Each read slices the seeded list to its requested limit, mirroring "the `$limit` most recent broadcasts".

## Errors

- `KitAuthException` — bad or missing API key.
- `KitRateLimitException` / `KitServerException` — transient; the client retries.
- `InvalidArgumentException` — a limit below 1 or above 100. Thrown locally before any request.

Full list: [Errors](Errors).
