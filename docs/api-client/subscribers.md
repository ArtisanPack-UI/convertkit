---
title: Subscribers
---

# Subscribers

Wraps Kit v4's `/subscribers` endpoints. Every method returns a `Subscriber` DTO (or `null` for lookups that can miss).

Access via `ConvertKit::subscribers()` or `convertkit()->subscribers()`.

## `create( string $email, ?string $firstName = null, array $fields = [] ): Subscriber`

Create a subscriber outside of any specific Kit form. Use this when you're subscribing a user to the account root, not to a particular list.

```php
$subscriber = ConvertKit::subscribers()->create(
    email: 'jane@example.com',
    firstName: 'Jane',
    fields: [ 'company' => 'Acme' ],
);
```

To subscribe to a specific Kit form (and optionally apply tags in one request), use [`forms()->subscribe()`](API-Client-Forms#subscribe) instead — it's a single API call vs. `create()` + separate tag applies.

## `find( int $id ): Subscriber`

Fetch a subscriber by their Kit ID.

```php
$subscriber = ConvertKit::subscribers()->find( 12345 );
```

Throws `KitNotFoundException` if Kit returns 404.

## `findByEmail( string $email ): ?Subscriber`

Look up by email. Returns `null` if Kit's search endpoint returns no match — no exception is thrown for a miss.

```php
$existing = ConvertKit::subscribers()->findByEmail( 'jane@example.com' );

if ( null === $existing ) {
    // Not yet subscribed.
}
```

## `update( int $id, array $attributes ): Subscriber`

Update a subscriber. Accepts any of `email_address`, `first_name`, `fields`.

```php
$updated = ConvertKit::subscribers()->update( $subscriber->id, [
    'first_name' => 'Jayne',
    'fields'     => [ 'company' => 'New Acme' ],
] );
```

## `tag( int $subscriberId, int $tagId ): void`

Apply a tag to a subscriber. Returns nothing — throws on failure.

```php
ConvertKit::subscribers()->tag( $subscriber->id, 100 );
```

If you're already calling `forms()->subscribe()` in the same flow, pass the tag ids in the `tags` argument there instead — Kit will apply them server-side in a single call, saving quota.

## `untag( int $subscriberId, int $tagId ): void`

Remove a tag.

```php
ConvertKit::subscribers()->untag( $subscriber->id, 100 );
```

## `unsubscribe( int $id ): Subscriber`

Unsubscribe the subscriber from all future emails. Returns the updated `Subscriber` DTO with `state = 'cancelled'`.

```php
$cancelled = ConvertKit::subscribers()->unsubscribe( $subscriber->id );
```

A missing subscriber id surfaces as `KitNotFoundException` from the client's 404 mapping.

## `toCollection( array $response ): PaginatedCollection<Subscriber>`

Utility for callers who invoke a raw list request via `ConvertKit::client()->get('subscribers', [...])`. Reconstructs a typed `PaginatedCollection<Subscriber>` from the raw JSON.

```php
$response  = ConvertKit::client()->get( 'subscribers', [ 'per_page' => 100 ] );
$collection = ConvertKit::subscribers()->toCollection( $response );

foreach ( $collection->items as $sub ) {
    // Subscriber
}
```

## Subscriber DTO

```php
final class Subscriber
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $state,       // active, cancelled, bounced
        public readonly ?string $firstName = null,
        public readonly ?string $createdAt = null,   // ISO 8601
        public readonly array $fields = [],
    ) {}
}
```

The DTO is immutable — build a new one if you need to model updates locally.

## Errors

- `KitAuthException` — bad or missing API key.
- `KitRateLimitException` — Kit throttled the call. Client retries up to `retries`; if the wait exceeds `max_retry_after`, this surfaces to the caller.
- `KitValidationException` — Kit rejected the payload (e.g. malformed email). Do not retry.
- `KitNotFoundException` — 404. Only fires on `find()`, `update()`, `tag()`, `untag()`, `unsubscribe()`. `findByEmail()` returns `null` instead.
- `KitServerException` — 5xx from Kit. The client retries with backoff.

Full list: [Errors](Errors).
