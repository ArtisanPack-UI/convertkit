---
title: Configuration
---

# Configuration

Publish the config file with:

```bash
php artisan vendor:publish --tag=convertkit-config
```

This copies `config/convertkit.php` into your app. Every env var it reads is documented at [Environment Variables](Installation-Environment-Variables).

## Top-level keys

### `api_key`

Your Kit v4 API key. Required for any HTTP call. Empty by default so the package installs without a Kit account.

```php
'api_key' => env( 'CONVERTKIT_API_KEY' ),
```

Read via `config('convertkit.api_key')` — never call `env()` outside of config files in your own code.

### `base_url`

Kit v4 base URL. Defaults to `https://api.kit.com/v4`. Override in tests or when Kit rolls out a regional endpoint.

### `timeout`

Per-request timeout in seconds. Default `15`. Bump this for slow networks or when uploading larger custom-field payloads.

### `retries`

How many times to retry a request that hits a `429` or `5xx`. Default `3`. Set to `0` in tests so retries don't slow the suite.

### `retry_delay`

Base delay between retries in milliseconds. Default `500`. The client uses exponential backoff on top of this and respects Kit's `Retry-After` header.

### `max_backoff`

Upper bound for a single retry wait (milliseconds). Default `30_000` (30 seconds). Prevents exponential backoff from stretching into multi-minute waits when `retries` is set high.

### `max_retry_after`

Upper bound for honoring a `Retry-After` header from Kit (seconds). Default `60`. If Kit asks you to wait longer than this, the client surfaces a `KitRateLimitException` immediately rather than pinning the worker.

### `allow_insecure_http`

Refuse to send the API key if `base_url` is not `https://` unless this is explicitly `true`. Default `false`. Keep this `false` in production.

## `cache`

Controls the reference-data cache (forms, tags, custom fields).

```php
'cache' => [
    'store'      => env( 'CONVERTKIT_CACHE_STORE' ),
    'prefix'     => 'convertkit',
    'forms_ttl'  => (int) env( 'CONVERTKIT_FORMS_TTL', 3600 ),
    'tags_ttl'   => (int) env( 'CONVERTKIT_TAGS_TTL', 3600 ),
    'fields_ttl' => (int) env( 'CONVERTKIT_FIELDS_TTL', 3600 ),
],
```

| Key | Purpose |
|---|---|
| `store` | Laravel cache store name. Null/empty falls back to the app default. |
| `prefix` | Cache-key prefix. The client also mixes in a hash of the API key so rotating credentials never serves stale data from a different account. |
| `forms_ttl` / `tags_ttl` / `fields_ttl` | TTL in seconds per endpoint. Defaults to 1 hour. |

Force-refresh with `php artisan convertkit:sync` — see [Artisan Commands](Artisan-Commands).

## `forms_integration`

Wires the package to [`artisanpack-ui/forms`](https://github.com/ArtisanPack-UI/forms). All coupling is config-driven so the forms package remains an optional peer.

```php
'forms_integration' => [
    'enabled'              => (bool) env( 'CONVERTKIT_FORMS_INTEGRATION', false ),
    'form_model'           => env( 'CONVERTKIT_FORMS_MODEL', '\\ArtisanPackUI\\Forms\\Models\\Form' ),
    'form_submitted_event' => env( 'CONVERTKIT_FORM_SUBMITTED_EVENT', '\\ArtisanPackUI\\Forms\\Events\\FormSubmitted' ),
    'queue_connection'     => env( 'CONVERTKIT_QUEUE_CONNECTION' ),
    'queue'                => env( 'CONVERTKIT_QUEUE' ),
],
```

| Key | Purpose |
|---|---|
| `enabled` | Master switch. When `false`, no feeds fire. |
| `form_model` | FQCN of the forms Form model, used by the `KitFeed::form()` relationship. |
| `form_submitted_event` | FQCN of the event dispatched by the forms package on submission. Subscribed via string binding so the class only needs to exist at runtime. |
| `queue_connection` / `queue` | Optional overrides for the `ProcessKitFeed` and `SubscribeToKit` jobs so ops can route Kit work to a dedicated queue. |

Full walkthrough: [Forms Integration](Forms-Integration).

## `feed_admin`

Controls the feed CRUD endpoints at `/admin/convertkit/*`.

```php
'feed_admin' => [
    'route_prefix' => env( 'CONVERTKIT_FEED_ADMIN_PREFIX', 'admin/convertkit' ),
    'middleware'   => [ 'web', 'auth' ],
    'gate_ability' => env( 'CONVERTKIT_FEED_ADMIN_ABILITY', 'manage-convertkit-feeds' ),
],
```

| Key | Purpose |
|---|---|
| `route_prefix` | URL prefix under which the feed routes are mounted. |
| `middleware` | Middleware stack applied to feed routes. `SubstituteBindings` is always prepended for implicit route-model binding. |
| `gate_ability` | Gate ability checked before every feed action. **Fails closed** — an empty value falls back to the package default `manage-convertkit-feeds`, never opens the API. |

Endpoint reference: [Feed Admin](REST-API-Feed-Admin).

## `subscribe`

Controls the public subscribe endpoint at `POST /convertkit/subscribers`.

```php
'subscribe' => [
    'route_prefix' => env( 'CONVERTKIT_SUBSCRIBE_PREFIX', 'convertkit' ),
    'middleware'   => [],
    'throttle'     => [
        'max_attempts'  => (int) env( 'CONVERTKIT_SUBSCRIBE_MAX_ATTEMPTS', 10 ),
        'decay_minutes' => (int) env( 'CONVERTKIT_SUBSCRIBE_DECAY_MINUTES', 1 ),
    ],
],
```

| Key | Purpose |
|---|---|
| `route_prefix` | URL prefix. Default `convertkit`, so the route lives at `POST /convertkit/subscribers`. |
| `middleware` | Additional middleware before the throttle. Leave empty for a fully public endpoint, or add `api` / auth guards. |
| `throttle.max_attempts` / `throttle.decay_minutes` | Per-IP rate-limit window. **Cannot be removed** by the consumer — the package prepends a `throttle:*` entry unless the consumer already supplies one. |

Endpoint reference: [Public Subscribe](REST-API-Subscribe).
