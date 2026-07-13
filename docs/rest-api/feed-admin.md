---
title: Feed Admin
---

# Feed Admin

CRUD endpoints for [`KitFeed`](Forms-Integration-Feeds) records. The package ships the endpoints — the UI is up to your app.

Base prefix: `admin/convertkit` (configurable via `CONVERTKIT_FEED_ADMIN_PREFIX`).

Every endpoint calls the [`manage-convertkit-feeds` Gate](REST-API#feed-admin-auth). A 403 with no body fires if it fails.

## `GET /admin/convertkit/feeds`

List every feed, ordered by id ASC.

**Query params:**

| Param | Purpose |
|---|---|
| `form_id` | Filter to feeds belonging to a specific form. |

**Response:**

```json
{
    "data": [
        {
            "id": 1,
            "form_id": 5,
            "name": "Newsletter",
            "kit_form_id": 12345,
            "kit_tag_ids": [100, 200],
            "field_map": { "email_address": "email", "first_name": "name" },
            "conditional_logic": null,
            "is_active": true,
            "created_at": "2026-07-13T14:22:00+00:00",
            "updated_at": "2026-07-13T14:22:00+00:00"
        }
    ]
}
```

Route name: `convertkit.feeds.index`.

## `POST /admin/convertkit/feeds`

Create a feed.

**Request body:**

```json
{
    "form_id": 5,
    "name": "Newsletter",
    "kit_form_id": 12345,
    "kit_tag_ids": [100, 200],
    "field_map": {
        "email_address": "email",
        "first_name": "name"
    },
    "conditional_logic": {
        "match": "all",
        "conditions": [
            { "field": "plan", "operator": "equals", "value": "pro" }
        ]
    },
    "is_active": true
}
```

**Validation rules** ([`KitFeedStoreRequest`](API-Client#namespace-map)):

| Field | Rules |
|---|---|
| `form_id` | required, integer, min:1 |
| `name` | required, string, max:255 |
| `kit_form_id` | nullable, integer, min:1 |
| `kit_tag_ids` | array; each item integer, min:1 |
| `field_map` | required, array, min:1; **must contain `email_address`** |
| `field_map.email_address` | required, string, max:255 |
| `field_map.*` | string, max:255 |
| `conditional_logic` | nullable, array |
| `conditional_logic.match` | nullable, string, in:`all`,`any` |
| `conditional_logic.conditions.*.field` | required with conditions, string |
| `conditional_logic.conditions.*.operator` | required with conditions, string, in: [supported operators](Forms-Integration-Conditional-Logic#operators) |
| `conditional_logic.conditions.*.value` | nullable |
| `is_active` | boolean |

**Response:** `201 Created` with the created feed under `data`.

Route name: `convertkit.feeds.store`.

## `GET /admin/convertkit/feeds/{id}`

Show a single feed.

**Response:** `200 OK` with the feed under `data`, or `404 Not Found` if the id doesn't exist.

Route name: `convertkit.feeds.show`.

## `PUT` / `PATCH /admin/convertkit/feeds/{id}`

Update a feed. All fields optional — the controller merges what was sent.

**Validation rules** ([`KitFeedUpdateRequest`](API-Client#namespace-map)) are the same as store, but with `sometimes` in place of `required`. The `field_map` still requires an `email_address` entry when present.

**Response:** `200 OK` with the updated feed under `data`.

Route name: `convertkit.feeds.update`.

## `DELETE /admin/convertkit/feeds/{id}`

Delete a feed.

**Response:** `204 No Content`.

Deletion does not touch subscribers already at Kit — see [Feeds#deleting-a-feed](Forms-Integration-Feeds#deleting-a-feed).

Route name: `convertkit.feeds.destroy`.

## Errors

- `422 Unprocessable Entity` — validation failed.
- `403 Forbidden` — Gate denied. The closure receives the resolved feed (or `null` for `index`/`store`) so you can key on ownership per record.
- `404 Not Found` — feed id doesn't exist (on show/update/delete/test).

## Building an admin UI on top

The package doesn't ship one. Common patterns:

- **Livewire** — a table backed by `GET /admin/convertkit/feeds` plus a form for `POST`/`PUT`. Use `<x-artisanpack-table>` and `<x-artisanpack-input>` from [`artisanpack-ui/livewire-ui-components`](https://github.com/ArtisanPack-UI/livewire-ui-components).
- **React / Vue** — same shape, from the SPA side. The endpoints are stateless JSON so no framework-specific wiring is needed.
- **Filament** — build a `KitFeed` resource on top of the model directly (skipping the REST layer) and rely on the same Gate.
