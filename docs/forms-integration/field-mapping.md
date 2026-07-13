---
title: Field Mapping
---

# Field Mapping

A feed's `field_map` translates a form submission's arbitrary field slugs into the shape Kit expects.

## Structure

Keys are Kit destinations, values are your submission's field slugs.

```json
{
    "email_address": "email",
    "first_name": "name",
    "company": "company_name",
    "plan_type": "plan"
}
```

Reading this map:

- The Kit key `email_address` gets its value from the submission field slugged `email`.
- The Kit key `first_name` gets its value from `name`.
- Everything else (`company`, `plan_type`) becomes a Kit **custom field** entry.

## Reserved keys

Two Kit destinations land at the top of the payload:

- `email_address` (required — see below)
- `first_name`

Everything else is treated as a custom-field key and moves into a nested `fields` object in the outgoing payload.

## Required: `email_address`

The `field_map` must include an `email_address` entry, and the mapped submission slug must resolve to a non-empty value.

- If the map is missing `email_address`, `FieldMapper` throws `FieldMapperException: Kit field map is missing the required 'email_address' destination.` This fires at store-request validation time — the [feed CRUD](REST-API-Feed-Admin) rejects the payload with a 422.
- If the map has `email_address` but the submission has no value for the mapped slug (or the value is empty), the mapper throws `FieldMapperException: Submission is missing a value for the mapped 'email_address' field.` The listener catches this and emits [`KitFeedSkipped`](Events#kitfeedskipped) with `reason` starting with `field_map:`.

Missing / unmapped **non-email** slugs are ignored — a partial submission is fine.

## Example

Feed config:

```json
{
    "field_map": {
        "email_address": "email",
        "first_name": "name",
        "company": "company_name"
    }
}
```

Submission values:

```json
{
    "email": "jane@example.com",
    "name": "Jane",
    "company_name": "Acme"
}
```

Mapper output:

```json
{
    "email_address": "jane@example.com",
    "first_name": "Jane",
    "fields": {
        "company": "Acme"
    }
}
```

## Finding your Kit custom-field keys

Custom-field keys are set in Kit's dashboard under **Grow → Subscribers → Custom Fields**. In your app, list them via:

```php
$fields = ConvertKit::customFields()->list();
```

The `key` on each `CustomField` is what you use as a `field_map` destination. Never the `label` or the `id`. See [Custom Fields](API-Client-Custom-Fields).

## Verifying your map

Before enabling a feed, dry-run it:

```
POST /admin/convertkit/feeds/{id}/test
{ "values": { "email": "test@example.com", ... } }
```

Response includes the exact `payload` that would be sent to Kit. See [Feed Dry-Run](REST-API-Dry-Run).

## Duck-typed submissions

The mapper is decoupled from the concrete `FormSubmission` model shipped by `artisanpack-ui/forms`. It accepts any object exposing values via:

1. A public `getValues()` method returning an array.
2. A `data_array` property (the forms package's default accessor).
3. A `data` collection with a `toArray()` method.
4. A public `values` property containing an array.

Falls back to `[]` if none match. This means you can invoke the mapper from a controller / job / test with a lightweight stub instead of standing up a real `FormSubmission`.

## Direct usage

If you're calling the mapper outside the forms integration (e.g. from a custom controller):

```php
use ArtisanPackUI\ConvertKit\Support\FieldMapper;

$mapper = app( FieldMapper::class );

// From an object (auto-detects the values accessor)
$payload = $mapper->map( $submission, $fieldMap );

// From a raw values array
$payload = $mapper->mapValues( [ 'email' => 'x@y.co' ], $fieldMap );
```

The listener calls `extractValues()` once per submission then uses `mapValues()` per feed, so N feeds cost one duck-type walk plus N pure array iterations.
