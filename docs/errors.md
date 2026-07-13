---
title: Errors
---

# Errors

Every Kit-side failure surfaces as a typed exception in the `ArtisanPackUI\ConvertKit\Api\Exceptions` namespace. All five extend `KitException`, which itself extends `RuntimeException`.

Catch `KitException` to catch everything; catch a specific subclass to route by failure kind.

## Hierarchy

```
KitException (base)
├── KitAuthException          — 401/403 from Kit
├── KitRateLimitException     — 429 (or Retry-After too long)
├── KitValidationException    — 422 with per-field errors
├── KitNotFoundException      — 404
└── KitServerException        — 5xx
```

## `KitAuthException`

- **When**: Kit returned `401 Unauthorized` or `403 Forbidden`.
- **Cause**: bad or missing API key, revoked key, or the key doesn't have permission for the endpoint.
- **Retry?**: **No.** Retrying with the same key will just fail again and waste quota.

**Fix:**

- Confirm `CONVERTKIT_API_KEY` is set and matches your Kit account.
- Run `php artisan convertkit:test` to verify.
- If you recently rotated the key, restart workers so the new value is picked up.

The client refuses to send the API key if `base_url` isn't `https://` unless `CONVERTKIT_ALLOW_INSECURE_HTTP=true` is explicitly set. That protects you from an accidental config that would leak credentials over plaintext.

## `KitRateLimitException`

- **When**: Kit returned `429 Too Many Requests`, or the `Retry-After` header asked for a wait longer than `convertkit.max_retry_after` (default 60s).
- **Cause**: too many requests too fast, or Kit is doing platform-level backpressure.
- **Retry?**: **Yes.** The client retries automatically with exponential backoff (`retries`, `retry_delay`, `max_backoff`) and honors `Retry-After` up to the max. If retries are exhausted, this bubbles to the caller.

**Fix:**

- If you see this frequently, raise `CONVERTKIT_RETRIES` or lower your request rate (batch subscribes, add a queue).
- The forms integration's `ProcessKitFeed` job re-throws this so the queue worker retries — no code change needed.

## `KitValidationException`

- **When**: Kit returned `422 Unprocessable Entity`.
- **Cause**: payload is malformed. Malformed email, unknown custom-field key, invalid `kit_form_id`, etc.
- **Retry?**: **No.** The payload will keep failing.

**Fix:**

- Log the exception message — Kit typically explains which field failed.
- If it happened via a feed, check the feed's `field_map`: are all the destination keys valid Kit custom fields? Run `php artisan convertkit:sync fields` and cross-reference.
- If it happened via `forms()->subscribe()`, confirm `kit_form_id` exists (`ConvertKit::forms()->list()`).

The `KitSubscriptionFailed` event fires when this comes out of a queued job. Route to your alerting stack — feed misconfig is usually the cause and needs human attention.

## `KitNotFoundException`

- **When**: Kit returned `404 Not Found`.
- **Cause**: the entity you addressed doesn't exist. A `kit_form_id` that was deleted, a subscriber id from a stale record, etc.
- **Retry?**: **No.**

**Fix:**

- `subscribers()->findByEmail()` returns `null` instead of throwing — reach for it when you're not sure the subscriber exists.
- For `find()` and `update()` calls, wrap in a try/catch if the entity might have been deleted between lookup and action.

## `KitServerException`

- **When**: Kit returned `500`, `502`, `503`, or `504`.
- **Cause**: Kit-side outage or blip.
- **Retry?**: **Yes.** The client retries automatically with exponential backoff.

**Fix:**

- Nothing you can do server-side. If retries are exhausted, this bubbles; `ProcessKitFeed` will re-throw and the queue worker will retry per its own retry policy.
- If Kit is fully down for long enough that queue retries drain, check [Kit's status page](https://status.kit.com/).

## Catching them

**Everything:**

```php
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitException;

try {
    ConvertKit::subscribers()->create( 'jane@example.com' );
} catch ( KitException $e ) {
    report( $e );
}
```

**Route by kind:**

```php
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitAuthException;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitRateLimitException;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitValidationException;

try {
    ConvertKit::subscribers()->create( 'jane@example.com' );
} catch ( KitAuthException $e ) {
    // Page an on-call engineer — bad or rotated key.
} catch ( KitRateLimitException $e ) {
    // Log; the client already retried.
} catch ( KitValidationException $e ) {
    // Report the exception; the request was malformed.
}
```

## Retry policy at a glance

| Exception | Client retries? | Queue job retries? |
|---|---|---|
| `KitAuthException` | No | No — job fails terminally. |
| `KitRateLimitException` | Yes | Yes — re-thrown from `handle()`. |
| `KitValidationException` | No | No — job fails terminally. |
| `KitNotFoundException` | No | No — job fails terminally. |
| `KitServerException` | Yes | Yes — re-thrown from `handle()`. |

## Non-Kit exceptions

- `FieldMapperException` — the [field mapper](Forms-Integration-Field-Mapping) could not produce a payload. Caught by the listener → `KitFeedSkipped` event.
- `RuntimeException` from `KitFeed::form()` — the configured `form_model` doesn't exist. Fix: install `artisanpack-ui/forms` or point `CONVERTKIT_FORMS_MODEL` at a real class.
- `\Illuminate\Contracts\Encryption\DecryptException` — Laravel's encrypter can't decrypt a token. Usually means `APP_KEY` was rotated without re-encrypting stored data.
