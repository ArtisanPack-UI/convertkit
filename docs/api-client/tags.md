---
title: Tags
---

# Tags

Wraps Kit v4's `/tags` endpoints. Access via `ConvertKit::tags()` or `convertkit()->tags()`.

The `list()` call is cached — see [reference-data caching](API-Client#reference-data-caching).

## `list(): array<int, Tag>`

Return every Kit tag, hitting the cache first.

```php
$tags = ConvertKit::tags()->list();

foreach ( $tags as $tag ) {
    echo "{$tag->id}: {$tag->name}\n";
}
```

Cache key: `{prefix}:{account}:tags`. TTL: `convertkit.cache.tags_ttl` (default 1 hour).

## `refresh(): array<int, Tag>`

Force a re-fetch and refresh the cache.

```php
$tags = ConvertKit::tags()->refresh();
```

Or from the CLI: `php artisan convertkit:sync tags`.

## Applying tags

The tags endpoint itself is read-only. To attach a tag to a subscriber, use one of:

- [`ConvertKit::subscribers()->tag($subscriberId, $tagId)`](API-Client-Subscribers#tag) — one call per tag.
- [`ConvertKit::forms()->subscribe($formId, $email, $fields, $tags)`](API-Client-Forms#subscribe) — subscribe + tag in a single call.

The [Forms Integration](Forms-Integration) applies feed tags automatically as part of the [job pipeline](Forms-Integration-Job-Pipeline).

## Tag DTO

```php
final class Tag
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $createdAt = null,
    ) {}
}
```

## Errors

- `KitAuthException` — bad or missing API key.
- `KitRateLimitException` / `KitServerException` — transient; the client retries.

Full list: [Errors](Errors).
