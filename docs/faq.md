---
title: FAQ
---

# FAQ

## Do I need `artisanpack-ui/forms` to use this package?

No. The [API client](API-Client) and the [public subscribe endpoint](REST-API-Subscribe) work without it. The forms package is only required for the [Forms Integration](Forms-Integration) (`FormSubmitted` → feed evaluation → queued job).

You can install `artisanpack-ui/convertkit` first and add `artisanpack-ui/forms` later — the listener is subscribed via string binding so the event class only needs to exist at runtime.

## Kit v3 vs v4

This package targets **Kit v4 exclusively**. A v3 API key will not work. Kit's v4 introduced a new authentication scheme and payload shape; there is no config toggle to fall back to v3.

If you're migrating, generate a v4 key in your Kit account under **Advanced → API** and update `CONVERTKIT_API_KEY`.

## Why does `subscribers()->findByEmail()` return null instead of throwing?

Because "not found" is a legitimate outcome of a lookup — you're usually asking "is this email already at Kit?" and want to branch on the answer. Every other method (`find`, `update`, `tag`, `untag`, `unsubscribe`) throws `KitNotFoundException` because they're actions against an id you claim to already know.

## Why does the public subscribe endpoint return 202 instead of 200?

The actual Kit call runs on the queue, so at the moment we respond the subscribe hasn't happened yet. `202 Accepted` is the correct semantic — "we received your request; we'll do the work." Success or failure surfaces via the [`KitSubscribed`](Events#kitsubscribed) and [`KitSubscriptionFailed`](Events#kitsubscriptionfailed) events, not the HTTP response.

If you need a synchronous confirmation, call the API client directly from your controller instead of routing through the public endpoint.

## Can I run the subscribe endpoint synchronously?

Set `QUEUE_CONNECTION=sync` and `SubscribeToKit` will run inside the HTTP request. You'll still get `202 Accepted` — the endpoint doesn't change its response shape based on the queue driver. Kit-side errors will surface via the `KitSubscriptionFailed` event as usual.

For a public-facing form under any real load, keep the queue asynchronous. A Kit outage should not take your homepage subscribe form down.

## Why is the rate limit 10/min per IP by default?

Because behind a corporate NAT or mobile carrier, dozens of legitimate users can share an IP. Ten is a compromise: high enough that a real subscribe form isn't affected in normal use, low enough that a scripted attacker can't easily fan out.

Tune via `CONVERTKIT_SUBSCRIBE_MAX_ATTEMPTS` and `CONVERTKIT_SUBSCRIBE_DECAY_MINUTES`. See [Public Subscribe#security](REST-API-Subscribe#security).

## Can I remove the rate limit entirely?

Not directly — the package prepends a `throttle:*` middleware unconditionally, on the grounds that a public endpoint without rate limiting is a foot-gun. You can, however, supply your own `throttle:*` entry in `convertkit.subscribe.middleware`; the package will detect it and skip the automatic prepend. That's the mechanism for wiring in a named Laravel rate limiter (e.g. one keyed on `Cloudflare-Client-IP` instead of `REMOTE_ADDR`).

## Why don't feed inactive-vs-unknown 422 responses distinguish?

Because they used to, and it acted as a feed-id enumeration oracle — an attacker could probe 1..N and diff the 202/422 mix to build a catalog of your live feeds. Consolidating the response removes that oracle. See [Public Subscribe#security](REST-API-Subscribe#security).

## Where does the API key get logged?

Never intentionally. The client redacts the `Authorization` header before logging any HTTP context, and no exception messages include the key.

The cache key does include a truncated SHA-256 of the API key — so rotating credentials doesn't serve stale data from a different Kit account — but the hash is not reversible.

## Does the package encrypt anything?

The Kit API key is stored in `.env` / `config/convertkit.php`, protected by whatever mechanism protects your app's secrets — Laravel's encrypter is not involved here.

The `convertkit_feeds` table's columns are stored plaintext (form id, name, kit form id, tag ids, field map, conditional logic, is_active). None of them are secrets — Kit form ids are essentially public and field maps are just column-name pairings.

## How do I test that a subscribe would fire without actually running it?

Two options:

- **`FakeConvertKit`** (unit-test level) — swap the whole binding for a recording fake. See [Testing](Testing).
- **Feed dry-run endpoint** (integration level) — hit `POST /admin/convertkit/feeds/{id}/test` with a sample submission and observe `would_send`. See [Feed Dry-Run](REST-API-Dry-Run).

## What happens if Kit is down for a long time?

- **Forms integration** — `KitRateLimitException` / `KitServerException` re-throw from the job so the queue worker retries. If the queue driver's own retries drain (24h on `redis`, up to `failed_jobs.exception` on `database`), the job lands in `failed_jobs` with a `KitSubscriptionFailed` event fired.
- **Public subscribe endpoint** — same job pipeline, same behavior.
- **API client direct calls** — `KitRateLimitException` / `KitServerException` bubble to the caller once retries are exhausted.

Recovery: once Kit is back, retry failed jobs with `php artisan queue:retry all` (or the ids from `failed_jobs`).

## Can I use this with Laravel Octane?

Yes. The service provider registers everything as singletons, so per-request state is not an issue. `FakeConvertKit::fake()` for tests still works — it rebinds the container instance directly.

## Do I need to worry about `APP_KEY` rotation?

Only if you're rotating `APP_KEY` for reasons unrelated to this package. Nothing in the ConvertKit package uses Laravel's `Encrypter` — API keys live in `.env`, feed rows are plaintext, and no user data is stored encrypted.

## What about GDPR / data retention?

The package doesn't store subscriber PII — every subscribe is fire-and-forget to Kit, with the queue payload (email, first name, custom fields) discarded once the job succeeds. If a job lands in `failed_jobs`, the payload is retained there for retry — clean up per your normal `failed_jobs` retention policy.

For user-initiated data-deletion, call `ConvertKit::subscribers()->unsubscribe($id)` (soft cancel at Kit) or issue a `DELETE /subscribers/{id}` via Kit's dashboard for hard deletion.

## How do I contribute?

See [Contributing](Contributing).
