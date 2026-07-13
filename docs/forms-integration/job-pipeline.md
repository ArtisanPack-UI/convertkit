---
title: Job Pipeline
---

# Job Pipeline

`ProcessKitFeed` is the job the [forms integration](Forms-Integration) dispatches, one per feed per submission. It's the actual thing that hits Kit.

The public subscribe endpoint dispatches a different job, [`SubscribeToKit`](REST-API-Subscribe#job-pipeline) — same shape, slightly different responsibilities. This page covers `ProcessKitFeed`.

## Constructor signature

```php
new ProcessKitFeed(
    KitFeed $feed,
    array $payload,        // ['email_address' => ..., 'first_name' => ?, 'fields' => [...]]
    array $tagIds,         // int|string ids to apply
    int $submissionId,     // for event payloads / logging
);
```

The job is dispatchable via the `Dispatchable` trait:

```php
ProcessKitFeed::dispatch( $feed, $payload, $tagIds, $submissionId );
```

## Retry policy

- `public int $tries = 3;`
- `public int $backoff = 30;` (seconds)

**Only retryable exceptions are re-thrown.** Every other failure is marked terminal immediately via `$this->fail($e)` — retrying would just burn quota.

| Exception | Behavior |
|---|---|
| `KitRateLimitException` | Re-thrown → queue worker retries after `backoff`. |
| `KitServerException` (5xx) | Re-thrown → retried. |
| `KitAuthException` (bad key) | Terminal — `failed()` fires. |
| `KitValidationException` (bad payload) | Terminal. |
| `KitNotFoundException` (bad kit_form_id) | Terminal. |
| Any other `Throwable` | Terminal. |

## `handle()`

Two paths, chosen by whether the feed has a Kit form set:

### With `kit_form_id`

```php
$convertKit->forms()->subscribe(
    $this->feed->kit_form_id,
    $email,
    $fields,
    $this->tagIds,
);
```

One Kit API call. Tags are applied server-side as part of the subscribe. `KitSubscribed` fires on success.

### Without `kit_form_id`

```php
$subscriber = $convertKit->subscribers()->create( $email, $firstName, $fields );

foreach ( $this->tagIds as $tagId ) {
    if ( is_numeric( $tagId ) ) {
        $convertKit->subscribers()->tag( $subscriber->id, (int) $tagId );
    }
}
```

`1 + N` calls (create + one per tag). Non-numeric tag ids are skipped silently — they can't be a legal Kit tag id.

## `failed()`

Fires `KitSubscriptionFailed` with the feed, payload, submission id, and exception:

```php
public function failed( Throwable $exception ): void
{
    KitSubscriptionFailed::dispatch( $this->feed, $this->payload, $this->submissionId, $exception );
}
```

By design, `KitSubscriptionFailed` fires **only** from `failed()`, never inline from `handle()`. That way subscribers see exactly one event per terminal failure — no duplicates from retries.

## Queue routing

The job reads `convertkit.forms_integration.queue_connection` and `queue` at construction time. This is baked into the dispatch payload — set once at boot, applies to every dispatch.

To route Kit work to a dedicated queue:

```dotenv
CONVERTKIT_QUEUE_CONNECTION=redis
CONVERTKIT_QUEUE=convertkit
```

Then run a dedicated worker: `php artisan queue:work redis --queue=convertkit`.

## Choosing `sync` vs. `database`/`redis`/`sqs`

- `sync` — the API call runs inside the HTTP request that dispatched it. If Kit is slow or down, your form submission is slow or down. **Avoid in production** for public-facing forms.
- `database` — persistent, works everywhere Laravel does, no extra infra. Good starter choice.
- `redis` / `sqs` — higher throughput. Recommended once you're doing more than a few subs a minute.

The retry policy is identical across drivers — retries live in the queue payload, not the driver.

## Empty payloads

Defense in depth: if the payload arrives with an empty `email_address`, the job throws `KitException: ProcessKitFeed received a payload with no email address` and `failed()` fires immediately without calling Kit. In practice, `FieldMapper` catches this at map time and the listener never dispatches — but the job double-checks.

## Events dispatched

- `KitSubscribed` — on successful subscribe.
- `KitSubscriptionFailed` — on terminal failure.

Neither is fired by the listener; both come from the job (or from `failed()`). See [Events](Events).

## The public subscribe job

For completeness: `SubscribeToKit` (used by [the public subscribe endpoint](REST-API-Subscribe)) has the same structure as `ProcessKitFeed` but takes primitives instead of a `KitFeed`. It also enforces a `MAX_TAGS_PER_JOB = 50` internal cap on the tag-apply loop as defense in depth against a buggy dispatcher.
