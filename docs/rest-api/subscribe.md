---
title: Public Subscribe
---

# Public Subscribe

The endpoint front-end subscribe forms POST to. The actual Kit call happens on the queue, so this always returns `202 Accepted` once validation passes.

## `POST /convertkit/subscribers`

**Auth:** none by default (that's the point). Per-IP throttle applied on top of any configured middleware.

**Route name:** `convertkit.subscribers.store`.

**Base prefix:** `convertkit` (configurable via `CONVERTKIT_SUBSCRIBE_PREFIX`).

## Two modes

The endpoint accepts either a `feed_id` (uses that feed's Kit form + tags) or a bare `kit_form_id` (skips the feed indirection). Exactly one of the two must be present — sending both is a validation error.

### `feed_id` mode

```json
{
    "feed_id": 1,
    "email": "jane@example.com",
    "first_name": "Jane",
    "tags": [ 100 ]
}
```

The controller resolves the feed (must be `is_active = true`), pulls its `kit_form_id`, merges its `kit_tag_ids` with any request-supplied `tags`, and dispatches the job. Missing `first_name` / `fields` / `tags` are fine — they're all optional.

### `kit_form_id` mode

```json
{
    "kit_form_id": 12345,
    "email": "jane@example.com",
    "first_name": "Jane",
    "fields": { "company": "Acme" },
    "tags": [ 7 ]
}
```

Skips the feed lookup entirely. Useful for one-off forms where you don't want to persist a `KitFeed` row.

## Validation rules

| Field | Rules |
|---|---|
| `feed_id` | required without `kit_form_id`, prohibits `kit_form_id`, integer, min:1 |
| `kit_form_id` | required without `feed_id`, integer, min:1 |
| `email` | required, valid email, max:255 |
| `first_name` | nullable, string, max:255 |
| `fields` | array, max:32 entries |
| `fields.*` | nullable, string, max:2048 |
| `tags` | array, max:20 |
| `tags.*` | integer, min:1 |

The `fields` and `tags` caps exist for a reason — see [Security](#security).

## Success response

```json
HTTP/1.1 202 Accepted
{ "message": "Subscribe queued." }
```

The actual Kit call runs on the worker. Success or failure surfaces via [`KitSubscribed`](Events#kitsubscribed) or [`KitSubscriptionFailed`](Events#kitsubscriptionfailed) events, not the HTTP response.

## Rate limit response

```json
HTTP/1.1 429 Too Many Requests
```

Standard Laravel `throttle` middleware response, including `Retry-After` and `X-RateLimit-*` headers.

## Validation failure

```json
HTTP/1.1 422 Unprocessable Entity
{
    "message": "The email field must be a valid email address.",
    "errors": {
        "email": [ "The email field must be a valid email address." ]
    }
}
```

## Job pipeline

Dispatches [`SubscribeToKit`](Forms-Integration-Job-Pipeline#the-public-subscribe-job). Signature:

```php
new SubscribeToKit(
    string $email,
    ?string $firstName,
    array $fields,
    array $tagIds,
    ?int $kitFormId,
);
```

Same retry policy as `ProcessKitFeed` — retries `KitRateLimitException` / `KitServerException`; every other exception marks the job failed immediately.

Defense-in-depth cap `SubscribeToKit::MAX_TAGS_PER_JOB = 50` on the tag-apply loop. Even if a future dispatcher forgets to bound its input, the job will not fan out more than 50 API calls per subscribe.

## Security

The endpoint is public — assume everyone can hit it. Guards:

- **Per-IP throttle** — default 10 attempts / minute per IP. Configurable but **cannot be removed** by the consumer (unless the consumer supplies their own `throttle:*` in `subscribe.middleware`, in which case the package defers to it).
- **`tags` capped at 20** — prevents an attacker from triggering thousands of Kit API calls per request via a huge `tags` array.
- **`fields` capped at 32 entries, each `string|max:2048`** — prevents queue-DB bloat via nested payloads or 10MB blobs.
- **`feed_id` inactive/unknown → generic 422** — the response is indistinguishable from any other validation failure, so 202 vs 422 does not act as a feed-id enumeration oracle.
- **`is_active = false` feeds are treated as unknown.** Toggle a feed off to instantly stop accepting subs via that feed_id.

## Example — curl

```bash
curl -X POST https://your-app.test/convertkit/subscribers \
    -H 'Content-Type: application/json' \
    -H 'Accept: application/json' \
    -d '{"feed_id": 1, "email": "jane@example.com"}'
```

## Recipes

Full front-end examples in [Subscribe Recipes](Subscribe-Recipes):

- [Livewire](Subscribe-Recipes-Livewire)
- [React](Subscribe-Recipes-React)
- [Vue](Subscribe-Recipes-Vue)

## Tuning the throttle

Loosen for a high-volume campaign:

```dotenv
CONVERTKIT_SUBSCRIBE_MAX_ATTEMPTS=60
CONVERTKIT_SUBSCRIBE_DECAY_MINUTES=1
```

Or route the endpoint behind your own limiter (e.g. Cloudflare rate limiting rules) and add your own `throttle:*` entry to `convertkit.subscribe.middleware`. The package will notice and skip prepending its own.

## When to use `feed_id` vs `kit_form_id`

- Use **`feed_id`** when you want the same admin/QA workflow you use for form-driven subs: create the feed once, dry-run it, toggle it on. Front-end code just references the feed id.
- Use **`kit_form_id`** when the front-end is calling Kit directly and you don't need the feed indirection. Kit form ids are essentially public (they're embedded in every Kit-hosted form URL), so leaking them via client JS is not a security concern.
