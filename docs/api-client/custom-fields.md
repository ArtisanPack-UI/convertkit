---
title: Custom Fields
---

# Custom Fields

Wraps Kit v4's `/custom_fields` endpoints. Access via `ConvertKit::customFields()` or `convertkit()->customFields()`.

The `list()` call is cached — see [reference-data caching](API-Client#reference-data-caching).

## `list(): array<int, CustomField>`

Return every custom field defined on the account, hitting the cache first.

```php
$fields = ConvertKit::customFields()->list();

foreach ( $fields as $field ) {
    echo "{$field->key} ({$field->label})\n";
}
```

Cache key: `{prefix}:{account}:fields`. TTL: `convertkit.cache.fields_ttl` (default 1 hour).

## `refresh(): array<int, CustomField>`

Force a re-fetch and refresh the cache. Useful right after adding a field in the Kit dashboard.

```php
$fields = ConvertKit::customFields()->refresh();
```

Or from the CLI: `php artisan convertkit:sync fields`.

## Setting custom field values

The custom fields endpoint itself is read-only — it exposes the field catalog so your app knows which keys are valid destinations. To actually write a value to a subscriber, include it in the `fields` argument of:

- [`ConvertKit::subscribers()->create($email, $firstName, $fields)`](API-Client-Subscribers#create)
- [`ConvertKit::subscribers()->update($id, ['fields' => $fields])`](API-Client-Subscribers#update)
- [`ConvertKit::forms()->subscribe($formId, $email, $fields, $tags)`](API-Client-Forms#subscribe)

The [Forms Integration](Forms-Integration) maps submission fields to Kit destinations via the feed's [`field_map`](Forms-Integration-Field-Mapping).

## CustomField DTO

```php
final class CustomField
{
    public function __construct(
        public readonly int $id,
        public readonly string $key,       // used in fields payloads
        public readonly string $label,     // human-readable
    ) {}
}
```

The `key` is what you pass in a `fields` payload — never the `label` and never the `id`. Kit rejects payloads that use unknown keys with `KitValidationException`.

## Errors

- `KitAuthException` — bad or missing API key.
- `KitRateLimitException` / `KitServerException` — transient; the client retries.

Full list: [Errors](Errors).
