---
title: Account
---

# Account

Wraps Kit v4's `account/growth_stats` endpoint. Access via `ConvertKit::account()` or `convertkit()->account()`.

Both reads are cached — see [reference-data caching](API-Client#reference-data-caching) — under a shorter TTL than the reference-data endpoints, because growth numbers move faster than forms/tags/fields.

## `stats( ?string $starting = null, ?string $ending = null ): GrowthStats`

Return the account's growth aggregate for a window: the subscriber count at the end of the window plus the new, cancelled, and net movement across it. Both bounds are optional — omit them and Kit reports the last 90 days.

```php
$stats = ConvertKit::account()->stats();                          // last 90 days
$stats = ConvertKit::account()->stats( '2026-01-01', '2026-03-31' );

$stats->subscribers;       // count at the end of the window
$stats->newSubscribers;    // gained over the window
$stats->cancellations;     // lost over the window
$stats->netNewSubscribers; // new minus cancellations
```

Dates use the `yyyy-mm-dd` format and are interpreted in the account's sending time zone (not UTC). A malformed date is rejected up front with `InvalidArgumentException` rather than being forwarded to Kit.

Cache key: `{prefix}:{account}:stats:{starting}:{ending}` (each omitted bound keyed as `default`). TTL: `convertkit.cache.stats_ttl` (default 15 minutes, override with `CONVERTKIT_STATS_TTL`).

## `growthSeries( string $starting, string $ending, string $interval = 'week' ): array<int, GrowthStats>`

Kit has no native time-series endpoint, so `growthSeries()` composes one by issuing a per-bucket `stats()` call across the range and returning one `GrowthStats` per bucket.

```php
$series = ConvertKit::account()->growthSeries( '2026-01-01', '2026-03-31', 'week' );

foreach ( $series as $point ) {
    echo "{$point->starting}: {$point->subscribers}\n";
}
```

- `interval` is one of `day`, `week`, or `month`. `week` buckets are rolling 7-day windows anchored to `$starting`; `month` buckets are calendar-aligned (so the first and last buckets of a mid-month range are partial).
- The whole series is cached under one key to amortize the per-bucket requests. Cache key: `{prefix}:{account}:stats:series:{starting}:{ending}:{interval}`.
- The range is capped at `AccountEndpoint::MAX_BUCKETS` (366) buckets. Because each bucket is its own API call, a wide range paired with a fine interval (e.g. years at `day` granularity) would otherwise fan out into thousands of sequential requests — a range past the cap throws `InvalidArgumentException`. An `ending` before `starting`, or an unsupported interval, throws the same.

## `refresh( ?string $starting = null, ?string $ending = null ): GrowthStats`

Force a re-fetch of a window's aggregate and refresh its cache entry.

```php
$stats = ConvertKit::account()->refresh();
```

## `refreshSeries( string $starting, string $ending, string $interval = 'week' ): array<int, GrowthStats>`

The series counterpart to `refresh()` — re-issues the per-bucket calls, ignoring any cached series, and stores the fresh result under the same key `growthSeries()` reads from. Range and interval are validated identically.

```php
$series = ConvertKit::account()->refreshSeries( '2026-01-01', '2026-03-31', 'week' );
```

## GrowthStats DTO

```php
final class GrowthStats
{
    public function __construct(
        public readonly int $subscribers,        // count at the end of the period
        public readonly int $netNewSubscribers,  // new minus cancellations
        public readonly int $newSubscribers,     // gained over the period
        public readonly int $cancellations,      // lost over the period
        public readonly ?string $starting = null, // ISO 8601, account time zone
        public readonly ?string $ending = null,   // ISO 8601, account time zone
    ) {}
}
```

## Testing

Under [`ConvertKit::fake()`](Testing) the endpoint is network-free: seed a fixture with `fakeStats()` / `fakeGrowthSeries()` and assert the reads with `assertStatsRequested()` / `assertGrowthSeriesRequested()`. Range and interval validation still runs against the requested window, but no Kit request is ever made.

## Errors

- `KitAuthException` — bad or missing API key.
- `KitRateLimitException` / `KitServerException` — transient; the client retries.
- `InvalidArgumentException` — a malformed date, an inverted range, an unsupported interval, or a range past the bucket cap. Thrown locally before any request.

Full list: [Errors](Errors).
