---
title: Feeds
---

# Feeds

A **feed** is the join between an [`artisanpack-ui/forms`](https://github.com/ArtisanPack-UI/forms) form and a Kit destination. One form can have many feeds — for example, one feed that sends every submission to a general newsletter, plus a second feed that only fires for enterprise plans and adds an `Enterprise Lead` tag.

## Schema

The `convertkit_feeds` table:

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | Primary key. |
| `form_id` | bigint | FK to the forms package's `forms` table. Resolved lazily via the `KitFeed::form()` relationship. |
| `name` | string | Human-readable label. Shown in admin UIs, logs, and CLI output. |
| `kit_form_id` | bigint, nullable | Kit form to subscribe to. `null` means the [job](Forms-Integration-Job-Pipeline) falls back to `subscribers()->create()` + per-tag applies. |
| `kit_tag_ids` | json | Array of Kit tag ids to apply. Default `[]`. |
| `field_map` | json | Kit destination → submission slug map. See [Field Mapping](Forms-Integration-Field-Mapping). |
| `conditional_logic` | json, nullable | Rules that must pass for the feed to fire. See [Conditional Logic](Forms-Integration-Conditional-Logic). |
| `is_active` | boolean | Master toggle. Inactive feeds are ignored by the listener and by the [public subscribe endpoint](REST-API-Subscribe). |
| `created_at` / `updated_at` | timestamps | |

## The `form()` relationship

The related model class is resolved from config at call time — `convertkit.forms_integration.form_model` — so the forms package remains an optional peer.

```php
$feed = KitFeed::find( 1 );
$form = $feed->form;   // ArtisanPackUI\Forms\Models\Form (or your custom model)
```

If the configured class doesn't exist, `KitFeed::form()` throws a descriptive `RuntimeException` at access time — never an opaque `Class "" not found` fatal.

## Creating a feed

Four options, from lowest to highest ceremony:

### CLI wizard

```bash
php artisan convertkit:feeds create
```

Walks you through form id, name, kit form id, email slug, and tags. Full docs: [Artisan Commands](Artisan-Commands).

### REST API

```
POST /admin/convertkit/feeds
```

Full spec: [Feed Admin](REST-API-Feed-Admin).

### Directly via Eloquent

```php
use ArtisanPackUI\ConvertKit\Models\KitFeed;

$feed = KitFeed::create( [
    'form_id'     => 5,
    'name'        => 'Newsletter',
    'kit_form_id' => 12345,
    'kit_tag_ids' => [ 100 ],
    'field_map'   => [
        'email_address' => 'email',
        'first_name'    => 'name',
    ],
    'is_active'   => true,
] );
```

### Via the factory (tests)

```php
KitFeed::factory()->create();
KitFeed::factory()->inactive()->create();
```

## Dry-running a feed

Before you flip a feed to `is_active = true`, dry-run it against a representative submission payload:

```
POST /admin/convertkit/feeds/{id}/test
{
    "values": { "email": "jane@example.com", "plan": "pro" }
}
```

Returns `{ would_send, reason, payload }` without touching Kit. Full spec: [Feed Dry-Run](REST-API-Dry-Run).

## Feed evaluation order

The listener loads feeds ordered by `id` ASC and evaluates them independently. There is no cross-feed short-circuit — a "wins" or "loses" feed does not affect the next one. If two feeds both match, they both fire.

## Inactive feeds

`is_active = false` is a hard stop:

- The listener skips inactive feeds entirely (no `KitFeedSkipped` event fires).
- The public subscribe endpoint returns a generic 422 for an inactive `feed_id` — see [Public Subscribe](REST-API-Subscribe#security).

Use this to pause a feed without deleting it. Feed history (dispatched jobs, `KitSubscribed` events) stays intact.

## Deleting a feed

Deletion removes the row but does not touch any subscribers already at Kit. If you need to also strip a tag from previously-subscribed users, you'll need a separate one-shot script that iterates and calls [`subscribers()->untag()`](API-Client-Subscribers#untag).

```bash
php artisan convertkit:feeds delete {id}
```

Asks for confirmation before deleting.
