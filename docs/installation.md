---
title: Installation
---

# Installation

## Install via Composer

```bash
composer require artisanpack-ui/convertkit
```

The package auto-registers via Laravel's package discovery:

- **Service provider**: `ArtisanPackUI\ConvertKit\ConvertKitServiceProvider`
- **Facade alias**: `ConvertKit` (`ArtisanPackUI\ConvertKit\Facades\ConvertKit`)
- **Helper**: `convertkit()`

No manual changes to `config/app.php` are required.

## Publish the config

```bash
php artisan vendor:publish --tag=convertkit-config
```

Publishes `config/convertkit.php`. Override the API key location, HTTP client settings, cache store, forms integration flags, feed admin routes, or public subscribe rate limits. Full reference: [Configuration](Installation-Configuration).

## Publish the migrations

```bash
php artisan vendor:publish --tag=convertkit-migrations
php artisan migrate
```

This creates:

- `convertkit_feeds` — the join between a form (from [`artisanpack-ui/forms`](https://github.com/ArtisanPack-UI/forms)) and Kit. See [Feeds](Forms-Integration-Feeds) for the schema.

If you're never going to use the forms integration, you can skip publishing the migration entirely — the [API Client](API-Client) works without any database tables.

## Set your API key

Generate a v4 key at [kit.com](https://kit.com/) under **Advanced → API**, then set it in `.env`:

```dotenv
CONVERTKIT_API_KEY=your-kit-v4-api-key
```

Verify the key can reach Kit:

```bash
php artisan convertkit:test
```

You should see the account name and plan. If not, see [Errors](Errors).

## Enable the forms integration (optional)

If you're pairing with [`artisanpack-ui/forms`](https://github.com/ArtisanPack-UI/forms):

```dotenv
CONVERTKIT_FORMS_INTEGRATION=true
CONVERTKIT_QUEUE_CONNECTION=redis
CONVERTKIT_QUEUE=convertkit
```

The listener is registered against the string form of the `FormSubmitted` event, so the forms package only needs to exist at runtime. See [Forms Integration](Forms-Integration).

## Configure route middleware

Two route groups ship with the package:

- **Feed admin routes** at `/admin/convertkit/*` — guarded by `web`, `auth`, and the `manage-convertkit-feeds` Gate ability by default. Override in config or via `CONVERTKIT_FEED_ADMIN_PREFIX` / `CONVERTKIT_FEED_ADMIN_ABILITY`.
- **Public subscribe route** at `POST /convertkit/subscribers` — guarded by a per-IP throttle (10/min by default). Override the prefix, middleware, and throttle window via config.

Full details: [REST API](REST-API).

## Verify the install

Run:

```bash
php artisan route:list --path=convertkit
```

You should see, at minimum:

```
GET    admin/convertkit/feeds                    convertkit.feeds.index
POST   admin/convertkit/feeds                    convertkit.feeds.store
GET    admin/convertkit/feeds/{convertkitFeed}   convertkit.feeds.show
PUT    admin/convertkit/feeds/{convertkitFeed}   convertkit.feeds.update
DELETE admin/convertkit/feeds/{convertkitFeed}   convertkit.feeds.destroy
POST   admin/convertkit/feeds/{convertkitFeed}/test convertkit.feeds.test
POST   convertkit/subscribers                    convertkit.subscribers.store
```

## Deeper topics

- [Requirements](Installation-Requirements) — PHP, Laravel, and peer-package versions in full detail.
- [Configuration](Installation-Configuration) — full `config/convertkit.php` reference.
- [Environment Variables](Installation-Environment-Variables) — every env var the package reads.

---
Continue to [API Client](API-Client) →
