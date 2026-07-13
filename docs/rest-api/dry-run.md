---
title: Feed Dry-Run
---

# Feed Dry-Run

Runs a feed's [conditional logic](Forms-Integration-Conditional-Logic) + [field mapper](Forms-Integration-Field-Mapping) against a sample submission payload and reports what would happen — without touching Kit.

Use this to sanity-check a feed's config while iterating.

## `POST /admin/convertkit/feeds/{id}/test`

**Auth:** [`manage-convertkit-feeds` Gate](REST-API#feed-admin-auth) (same as CRUD). Not rate-limited.

**Route name:** `convertkit.feeds.test`.

**Request body:**

```json
{
    "values": {
        "email": "jane@example.com",
        "name": "Jane",
        "plan": "pro"
    }
}
```

Keys in `values` are your submission's field slugs — the same shape the [field mapper](Forms-Integration-Field-Mapping) expects. `values` is required and must be an array.

**Response:** always `200 OK` (unless auth fails or the feed doesn't exist). Body shape:

```json
{
    "would_send": true|false,
    "reason": "conditional_logic" | "field_map" | null,
    "payload": { ... } | null
}
```

## Response shapes

### Feed would fire

```json
{
    "would_send": true,
    "reason": null,
    "payload": {
        "email_address": "jane@example.com",
        "first_name": "Jane",
        "fields": {
            "plan_type": "pro"
        }
    }
}
```

`payload` is the exact object that would be sent to Kit if you dispatched a real subscribe.

### Conditional logic blocks the feed

```json
{
    "would_send": false,
    "reason": "conditional_logic",
    "payload": null
}
```

The feed's conditional_logic evaluated to `false`. Adjust your rules or the submission values.

### Field map cannot produce a payload

```json
{
    "would_send": false,
    "reason": "field_map",
    "payload": null
}
```

The [field mapper](Forms-Integration-Field-Mapping) threw — usually because the submission has no value for the slug mapped to `email_address`, or the map itself is missing `email_address`. The underlying exception message is logged server-side via `Log::warning` with the feed id and the raw error; the response body deliberately does not surface it so this endpoint can't be turned into an information oracle.

## Errors

- `422 Unprocessable Entity` — `values` was missing or not an array.
- `403 Forbidden` — Gate denied.
- `404 Not Found` — feed id doesn't exist.

## Example — curl

```bash
curl -X POST https://your-app.test/admin/convertkit/feeds/1/test \
    -H 'Content-Type: application/json' \
    -H 'Accept: application/json' \
    -H 'X-CSRF-TOKEN: <token>' \
    -d '{"values": {"email": "jane@example.com", "plan": "pro"}}'
```

## Example — from a Livewire admin UI

```php
public function testFeed(): void
{
    $response = Http::withCookies( request()->cookies->all(), request()->getHost() )
        ->post( route( 'convertkit.feeds.test', $this->feedId ), [
            'values' => $this->sampleValues,
        ] );

    $this->result = $response->json();
}
```

Or invoke the mapper + evaluator directly if you're already server-side:

```php
use ArtisanPackUI\ConvertKit\Models\KitFeed;
use ArtisanPackUI\ConvertKit\Support\ConditionalLogicEvaluator;
use ArtisanPackUI\ConvertKit\Support\FieldMapper;

$feed      = KitFeed::find( $feedId );
$evaluator = app( ConditionalLogicEvaluator::class );
$mapper    = app( FieldMapper::class );

if ( ! $evaluator->evaluate( $feed->conditional_logic, $values ) ) {
    // conditional_logic reason
}

$payload = $mapper->mapValues( $values, $feed->field_map );
```

Skips the HTTP round-trip when you don't need the JSON envelope.
