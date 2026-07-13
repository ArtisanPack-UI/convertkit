---
title: Forms
---

# Forms

Wraps Kit v4's `/forms` endpoints. Access via `ConvertKit::forms()` or `convertkit()->forms()`.

The `list()` call is cached — see [reference-data caching](API-Client#reference-data-caching).

## `list(): array<int, Form>`

Return every Kit form, hitting the cache first.

```php
$forms = ConvertKit::forms()->list();

foreach ( $forms as $form ) {
    echo "{$form->id}: {$form->name}\n";
}
```

Cache key: `{prefix}:{account}:forms`. TTL: `convertkit.cache.forms_ttl` (default 1 hour).

## `refresh(): array<int, Form>`

Force a re-fetch and refresh the cache. Use this after creating a form in Kit's dashboard when you want your app to see it immediately.

```php
$forms = ConvertKit::forms()->refresh();
```

Or from the CLI: `php artisan convertkit:sync forms`.

## `subscribe( int $formId, string $email, array $fields = [], array $tags = [] ): Subscriber`

Subscribe an email to a specific Kit form. Kit applies the tags server-side in the same request — no follow-up call needed.

```php
$subscriber = ConvertKit::forms()->subscribe(
    formId: 12345,
    email: 'jane@example.com',
    fields: [ 'company' => 'Acme' ],
    tags: [ 100, 200 ],
);
```

Prefer this over `subscribers()->create()` + separate `subscribers()->tag()` calls — one Kit API call vs. N+1.

If the target Kit form is set to double-opt-in, Kit sends the confirmation email itself; the returned `Subscriber` will still be in `active` state (Kit tracks confirmation separately).

## Form DTO

```php
final class Form
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $type,       // hosted, embed, sticky_bar, modal, ...
        public readonly ?string $embedUrl = null,
        public readonly ?string $createdAt = null,
    ) {}
}
```

## Errors

- `KitValidationException` — Kit rejected the payload (invalid form id, malformed email, unknown field key).
- `KitNotFoundException` — 404 on the form.
- `KitRateLimitException` / `KitServerException` — transient; the client retries with backoff.
- `KitAuthException` — bad or missing API key.

Full list: [Errors](Errors).
