---
title: API Client
---

# API Client

The fluent entry point for talking to the Kit v4 API.

Sub-pages by endpoint:

- [Subscribers](API-Client-Subscribers)
- [Forms](API-Client-Forms)
- [Tags](API-Client-Tags)
- [Custom Fields](API-Client-Custom-Fields)
- [Account](API-Client-Account)
- [Broadcasts](API-Client-Broadcasts)

## The facade

```php
use ArtisanPackUI\ConvertKit\Facades\ConvertKit;

ConvertKit::subscribers();    // SubscribersEndpoint
ConvertKit::forms();          // FormsEndpoint
ConvertKit::tags();           // TagsEndpoint
ConvertKit::customFields();   // CustomFieldsEndpoint
ConvertKit::account();        // AccountEndpoint (stats)
ConvertKit::broadcasts();     // BroadcastsEndpoint (read-only)
ConvertKit::client();         // low-level Client
```

Every accessor returns a singleton — the same instance is shared across the request lifecycle. The `ConvertKit` class itself just aggregates the endpoint wrappers.

## The helper

```php
convertkit();  // same as app( 'convertkit' )
```

Returns the `ArtisanPackUI\ConvertKit\ConvertKit` instance. Equivalent to using the facade — pick whichever style matches your codebase.

## Container bindings

The service provider registers:

| Abstract | Concrete | Scope |
|---|---|---|
| `convertkit` | `ArtisanPackUI\ConvertKit\ConvertKit` | Singleton |
| `ArtisanPackUI\ConvertKit\ConvertKit::class` | Same | Singleton |
| `ArtisanPackUI\ConvertKit\Api\Client::class` | Configured with API key, base URL, retry policy | Singleton |
| `ArtisanPackUI\ConvertKit\EndpointFactory::class` | Builds endpoint wrappers | Singleton |

To swap the whole thing for tests, use [`ConvertKit::fake()`](Testing).

## Return types

Every endpoint returns immutable DTOs from the `ArtisanPackUI\ConvertKit\Api\DTOs` namespace:

- `Subscriber` — id, email, state, first name, created-at, fields.
- `Form` — id, name, type, embed URL, created-at.
- `Tag` — id, name, created-at.
- `CustomField` — id, key, label.
- `GrowthStats` — subscriber count plus new/cancelled/net movement for a window.
- `Broadcast` — id, subject, send-at, and a nested `BroadcastStats`.
- `BroadcastStats` — recipients, opens/clicks, open/click/unsubscribe rates, progress, status.

List endpoints return `PaginatedCollection<T>` with `meta` (per-page, page, total) and an `items` iterator.

## Exceptions

Every HTTP-level failure surfaces a typed `KitException` subclass — see [Errors](Errors) for the hierarchy and when each fires.

## Reference-data caching

`forms()->list()`, `tags()->list()`, and `customFields()->list()` cache their results under `{prefix}:{account}:{name}` in the configured store. Force a refresh with:

```php
ConvertKit::forms()->refresh();
ConvertKit::tags()->refresh();
ConvertKit::customFields()->refresh();
```

Or from the CLI: `php artisan convertkit:sync [resource]`.

The [`account()`](API-Client-Account) and [`broadcasts()`](API-Client-Broadcasts) reads are cached the same way, keyed per window/interval and per limit respectively. Growth stats move faster than reference data, so `stats_ttl` defaults to 15 minutes; `broadcasts_ttl` follows the reference-data default of 1 hour. Force a refresh with `account()->refresh()` / `account()->refreshSeries()` and `broadcasts()->refresh()`.

The `subscribers()` endpoint is intentionally uncached — subscriber state is high-churn.

## Namespace map

```
ArtisanPackUI\ConvertKit\
├── ConvertKit.php                       — the aggregator
├── ConvertKitServiceProvider.php
├── EndpointFactory.php
├── helpers.php                          — convertkit()
├── Facades\
│   └── ConvertKit.php
├── Api\
│   ├── Client.php                       — low-level HTTP client
│   ├── DTOs\
│   │   ├── Subscriber.php
│   │   ├── Form.php
│   │   ├── Tag.php
│   │   ├── CustomField.php
│   │   ├── GrowthStats.php
│   │   ├── Broadcast.php
│   │   ├── BroadcastStats.php
│   │   └── PaginatedCollection.php
│   ├── Endpoints\
│   │   ├── SubscribersEndpoint.php
│   │   ├── FormsEndpoint.php
│   │   ├── TagsEndpoint.php
│   │   ├── CustomFieldsEndpoint.php
│   │   ├── AccountEndpoint.php
│   │   └── BroadcastsEndpoint.php
│   └── Exceptions\
│       ├── KitException.php             — base
│       ├── KitAuthException.php
│       ├── KitRateLimitException.php
│       ├── KitValidationException.php
│       ├── KitNotFoundException.php
│       └── KitServerException.php
├── Models\
│   └── KitFeed.php                      — Forms Integration
├── Support\
│   ├── FieldMapper.php                  — Forms Integration
│   ├── FieldMapperException.php
│   └── ConditionalLogicEvaluator.php    — Forms Integration
├── Jobs\
│   ├── ProcessKitFeed.php               — Forms Integration
│   └── SubscribeToKit.php               — Public Subscribe
├── Events\
│   ├── KitSubscribed.php
│   ├── KitSubscriptionFailed.php
│   └── KitFeedSkipped.php
├── Listeners\
│   └── HandleFormSubmittedForKit.php
├── Console\
│   ├── TestCommand.php                  — convertkit:test
│   ├── SyncCommand.php                  — convertkit:sync
│   └── FeedsCommand.php                 — convertkit:feeds
├── Http\
│   ├── Controllers\
│   │   ├── FeedController.php
│   │   └── SubscribeController.php
│   ├── Requests\
│   │   ├── KitFeedStoreRequest.php
│   │   ├── KitFeedUpdateRequest.php
│   │   ├── KitFeedTestRequest.php
│   │   └── SubscribeRequest.php
│   └── Resources\
│       └── KitFeedResource.php
└── Testing\
    ├── FakeConvertKit.php
    ├── FakeSubscribersEndpoint.php
    ├── FakeFormsEndpoint.php
    ├── FakeTagsEndpoint.php
    ├── FakeCustomFieldsEndpoint.php
    ├── FakeAccountEndpoint.php
    └── FakeBroadcastsEndpoint.php
```

---
Continue to [Subscribers](API-Client-Subscribers) →
