---
title: REST API
---

# REST API

Three REST endpoints ship with the package.

Sub-pages by endpoint:

- [Feed Admin CRUD](REST-API-Feed-Admin) — list, show, create, update, delete feeds.
- [Feed Dry-Run](REST-API-Dry-Run) — evaluate a feed against a sample submission without touching Kit.
- [Public Subscribe](REST-API-Subscribe) — queue an email subscribe from any front-end.

## Route summary

| Method | Path | Purpose | Auth |
|---|---|---|---|
| `GET` | `/admin/convertkit/feeds` | List feeds | Gate |
| `POST` | `/admin/convertkit/feeds` | Create feed | Gate |
| `GET` | `/admin/convertkit/feeds/{id}` | Show feed | Gate |
| `PUT` / `PATCH` | `/admin/convertkit/feeds/{id}` | Update feed | Gate |
| `DELETE` | `/admin/convertkit/feeds/{id}` | Delete feed | Gate |
| `POST` | `/admin/convertkit/feeds/{id}/test` | Dry-run feed | Gate |
| `POST` | `/convertkit/subscribers` | Public subscribe | Throttle |

All prefixes are configurable — see [Configuration](Installation-Configuration#feed_admin) and [Configuration](Installation-Configuration#subscribe).

## Feed admin auth

Every admin endpoint calls `Gate::allows($ability, [$feed])` where `$ability` is `convertkit.feed_admin.gate_ability` (default `manage-convertkit-feeds`). Define your own gate:

```php
use ArtisanPackUI\ConvertKit\Models\KitFeed;

Gate::define(
    'manage-convertkit-feeds',
    fn ( User $user, ?KitFeed $feed = null ): bool => $user->isAdmin(),
);
```

The gate closure receives the resolved `KitFeed` (or `null` for `index` / `store`) so you can key access per record.

**Fails closed.** An empty `gate_ability` config value does not disable auth — it falls back to the built-in default so a blank env var never opens the API.

## Public subscribe auth

The subscribe endpoint is intentionally public — that's the whole point of an email-capture form. Two guards:

1. **Per-IP throttle** applied by the package. Default 10/min. Configurable via env; **cannot be removed** by the consumer, only tuned.
2. **Kit-side controls.** If the target Kit form requires double opt-in, Kit sends the confirmation email itself. If a subscriber tries to re-subscribe, Kit handles idempotence.

If you need auth on top of the throttle (say, an API-token flow), add middleware via `convertkit.subscribe.middleware`.

## Response format

Feed admin endpoints return Laravel API resources — every response is wrapped in a `data` key:

```json
{
    "data": { "id": 1, "form_id": 5, "name": "Newsletter", ... }
}
```

Except `DELETE`, which returns 204 with no body.

The dry-run endpoint returns a flat JSON object (no `data` wrapper):

```json
{ "would_send": true, "reason": null, "payload": { ... } }
```

The public subscribe endpoint returns `202 Accepted` with a simple message:

```json
{ "message": "Subscribe queued." }
```

## Errors

- `422 Unprocessable Entity` — validation errors, in Laravel's standard shape `{ message, errors: {...} }`.
- `403 Forbidden` — gate denied. No body.
- `404 Not Found` — feed not found (only on show/update/delete/test).
- `429 Too Many Requests` — throttle hit.

Kit-side errors (auth, validation, 5xx) happen inside the queued job, not the request cycle. See [Events](Events#kitsubscriptionfailed) for how to react.

---
Continue to [Feed Admin](REST-API-Feed-Admin) →
