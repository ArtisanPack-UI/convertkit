# ArtisanPack UI ConvertKit Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.0] - 2026-08-23

### Added

- **Account stats endpoint** — `ConvertKit::account()->stats()` returns the
  account's growth aggregate (subscriber count plus new/cancelled/net movement)
  for an optional `yyyy-mm-dd` window via Kit's `account/growth_stats` endpoint,
  as an immutable `GrowthStats` DTO. `->growthSeries( $starting, $ending, $interval )`
  composes a `day`/`week`/`month` time series from per-bucket calls, capped at
  `AccountEndpoint::MAX_BUCKETS` (366) to bound the fan-out. Both reads are
  cached (`stats_ttl`, default 15 minutes, overridable with
  `CONVERTKIT_STATS_TTL`), keyed per window/interval; `->refresh()` and
  `->refreshSeries()` force a re-fetch. Window dates are validated up front, so
  a malformed date is rejected rather than forwarded to Kit. Covered by
  `ConvertKit::fake()` via a network-free `FakeAccountEndpoint`.
- **Read-only broadcasts endpoint** — `ConvertKit::broadcasts()->list()`
  returns recent broadcasts paired with their delivery/engagement stats
  (recipients, open/click rates, unsubscribes) via Kit's `broadcasts/stats`
  endpoint, in a single cursor-paginated request. Returns immutable
  `Broadcast` DTOs each carrying a `BroadcastStats` DTO. Results are cached
  like the reference-data endpoints (`broadcasts_ttl`, default 1 hour,
  overridable with `CONVERTKIT_BROADCASTS_TTL`), keyed per limit; use
  `->refresh()` to force a re-fetch. The limit defaults to 10 and is capped at
  `BroadcastsEndpoint::MAX_LIMIT` (100). Covered by `ConvertKit::fake()` via a
  network-free `FakeBroadcastsEndpoint`.
- **Expanded conditional logic operators** — `ConditionalLogicEvaluator` now
  handles eighteen operators, adding `starts_with`, `ends_with`,
  `greater_than`, `less_than`, `greater_or_equal`, `less_or_equal`, `in`,
  `not_in`, `checked`, `unchecked`, `includes`, and `not_includes` to the
  original six. The set is exposed as
  `ConditionalLogicEvaluator::OPERATORS` and drives feed store/update request
  validation, so the accepted operators can never drift from the evaluated ones.
- **Test fixtures for stats and broadcasts** — `ConvertKit::fake()` gains the
  `fakeStats()`, `fakeGrowthSeries()`, and `fakeBroadcasts()` seeders plus the
  `assertStatsRequested()`, `assertGrowthSeriesRequested()`, and
  `assertBroadcastsListed()` assertions.

### Fixed

- `KitFeed` now declares its attribute casts as a `$casts` property rather than
  the `casts()` method. `casts()` only exists from Laravel 11, so on Laravel 10
  — still an advertised target — the method form silently disabled every cast,
  leaving JSON columns as raw strings and `is_active` uncast.

## [1.1.0] - 2026-07-21

### Added

- **Subscribe lifecycle hooks** — the `SubscribeToKit` job now fires
  three ArtisanPack UI hooks (`artisanpack-ui/hooks: ^1.3`):
  - `ap.convertkit.subscribing` before the Kit API call, receiving
    `(string $email, array $attributes)` where `$attributes` is the
    payload map (`first_name`, `fields`, `tag_ids`, `kit_form_id`).
  - `ap.convertkit.subscribed` after a successful subscribe, receiving
    `(string $email, array $response)` where `$response` is the
    subscriber decoded to an array.
  - `ap.convertkit.subscribeFailed` on any exception, receiving
    `(string $email, Throwable $exception)`. Fires per attempt, so
    retryable failures emit one hook per retry.

### Changed

- `artisanpack-ui/hooks: ^1.3` is now a required runtime dependency.

## [1.0.0] - 2026-07-13

Initial release. Full Kit v4 API integration for Laravel 10, 11, 12, and 13.

### Added

- **Kit v4 API client** with exponential-backoff retries, `Retry-After` handling,
  and typed exceptions (`KitAuthException`, `KitRateLimitException`,
  `KitValidationException`, `KitNotFoundException`, `KitServerException`).
- **Endpoint wrappers** for subscribers, forms, tags, and custom fields.
  Each returns immutable DTOs (`Subscriber`, `Form`, `Tag`, `CustomField`) or
  `PaginatedCollection` for list responses.
- **Reference-data cache** for forms, tags, and custom-field lists with a
  per-API-key cache key so rotating credentials never serves stale data from
  a different Kit account.
- **`ConvertKit` facade + `convertkit()` helper** as the fluent entry point.
- **Forms integration** with `artisanpack-ui/forms`:
  - `KitFeed` model — one row per (form, Kit destination) join.
  - `HandleFormSubmittedForKit` listener — subscribed via string binding so
    the forms package remains an optional peer.
  - `ConditionalLogicEvaluator` — six operators (`equals`, `not_equals`,
    `contains`, `not_contains`, `is_empty`, `is_not_empty`) with `all` (AND)
    or `any` (OR) semantics.
  - `FieldMapper` — translates a submission's slugs into the Kit payload
    shape; duck-typed for the concrete `FormSubmission` model.
  - `ProcessKitFeed` job — retries only on transient failures
    (`KitRateLimitException`, `KitServerException`); every other exception
    marks the job failed immediately to avoid burning Kit quota.
- **REST endpoints**:
  - `GET|POST|PUT|PATCH|DELETE /admin/convertkit/feeds[/{id}]` — feed CRUD,
    guarded by the `manage-convertkit-feeds` Gate ability (fails closed on
    empty config).
  - `POST /admin/convertkit/feeds/{id}/test` — dry-run a feed against a
    sample submission, reports `{would_send, reason, payload}` without
    touching Kit.
  - `POST /convertkit/subscribers` — public subscribe endpoint front-end
    forms POST to. Accepts either `feed_id` (uses the feed's config) or a
    bare `kit_form_id`. Dispatches `SubscribeToKit` on the queue, returns
    202. Rate limited per-IP (default 10/min).
- **`SubscribeToKit` job** with the same retry policy as `ProcessKitFeed`,
  plus a `MAX_TAGS_PER_JOB = 50` defense-in-depth cap on the tag-apply loop.
- **Artisan commands**:
  - `convertkit:test` — verify the configured API key reaches Kit.
  - `convertkit:sync [resource]` — force-refresh cached forms, tags, and
    custom fields.
  - `convertkit:feeds {list|create|delete}` — CLI feed management, with an
    interactive wizard for create and confirmation prompt for delete.
- **Events**:
  - `KitSubscribed` — a subscribe call to Kit succeeded.
  - `KitSubscriptionFailed` — a job failed terminally; fires exactly once
    per failure.
  - `KitFeedSkipped` — a feed was loaded but not fired; reason codes
    include `conditional_logic`, `field_map`, `evaluator_error`, and
    `dispatch_error`.
- **`FakeConvertKit`** — recording test double installed via
  `ConvertKit::fake()`. Assertion helpers: `assertSubscribed`,
  `assertTagged`, `assertNothingSent`, `assertSentCount`.
- **Documentation** — 30 wiki-style pages under `docs/` covering install,
  configuration, API client, forms integration, REST endpoints, subscribe
  recipes (Livewire / React / Vue), Artisan commands, events, errors,
  testing, FAQ, and contributing.
- **CI** — GitHub Actions matrix testing Laravel 12 (PHP 8.2/8.3/8.4) and
  Laravel 13 (PHP 8.3/8.4). Lint job runs PHP-CS-Fixer and PHPCS. Release
  workflow tags and notifies Packagist.

### Security

- Public subscribe endpoint enforces `tags` array size ≤ 20 and `fields`
  size ≤ 32 with each value `string|max:2048` — closes quota-drain and
  queue-bloat vectors.
- Unknown/inactive `feed_id` returns Laravel's standard validation error
  shape so the endpoint doesn't act as a feed-id enumeration oracle.
- Feed dry-run endpoint returns a stable `reason` token; the underlying
  exception message is logged server-side via `Log::warning`, not returned
  to the client.
- Feed admin routes fail closed on empty `gate_ability` config — an empty
  ability falls back to the built-in default rather than opening the API.
- Public subscribe route throttle prepended unconditionally, unless the
  consumer supplies their own `throttle:*` middleware (which the package
  detects and defers to).

[Unreleased]: https://github.com/ArtisanPack-UI/convertkit/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/ArtisanPack-UI/convertkit/releases/tag/v1.0.0
