---
title: Getting Started
---

# Getting Started

Welcome to ArtisanPack UI ConvertKit. This guide walks through the shortest path from `composer require` to a queued subscribe hitting Kit.

See also: [Installation](Installation), [API Client](API-Client), [Forms Integration](Forms-Integration), and [REST API](REST-API).

## Requirements

- PHP 8.2+
- Laravel 10.x, 11.x, 12.x, or 13.x
- A [Kit](https://kit.com/) account with a v4 API key

Full details: [Requirements](Installation-Requirements).

## 1. Install

```bash
composer require artisanpack-ui/convertkit
```

The service provider and `ConvertKit` facade are auto-discovered.

## 2. Publish config and migrations

```bash
php artisan vendor:publish --tag=convertkit-config
php artisan vendor:publish --tag=convertkit-migrations
php artisan migrate
```

This creates `config/convertkit.php` and the `convertkit_feeds` table.

## 3. Add your API key

Generate a v4 key in your Kit account under **Advanced → API**, then set it in `.env`:

```dotenv
CONVERTKIT_API_KEY=your-kit-v4-api-key
```

Verify the key can reach Kit:

```bash
php artisan convertkit:test
```

You should see the account name and plan. See [Artisan Commands](Artisan-Commands) for the full command list.

## 4. Make an API call

The `convertkit()` helper (or the `ConvertKit` facade) is the fluent entry point:

```php
use ArtisanPackUI\ConvertKit\Facades\ConvertKit;

$subscriber = ConvertKit::subscribers()->create(
    email: 'jane@example.com',
    firstName: 'Jane',
    fields: [ 'company' => 'Acme' ],
);
```

More: [Subscribers](API-Client-Subscribers), [Forms](API-Client-Forms), [Tags](API-Client-Tags), [Custom Fields](API-Client-Custom-Fields).

## 5. (Optional) Wire up the forms integration

If you're using [`artisanpack-ui/forms`](https://github.com/ArtisanPack-UI/forms), flip on the integration and every submission of a form the [feed](Forms-Integration-Feeds) matches is evaluated, mapped, and dispatched to a queue:

```dotenv
CONVERTKIT_FORMS_INTEGRATION=true
CONVERTKIT_QUEUE_CONNECTION=redis
CONVERTKIT_QUEUE=convertkit
```

Then create a feed via the CLI wizard:

```bash
php artisan convertkit:feeds create
```

Full walkthrough: [Forms Integration](Forms-Integration).

## 6. (Optional) Add a public subscribe endpoint

If your front-end is React, Vue, or plain HTML/JS, POST to the built-in public endpoint:

```
POST /convertkit/subscribers
{
    "kit_form_id": 12345,
    "email": "jane@example.com"
}
```

Rate limited by IP (default 10/min). Full spec: [Public Subscribe](REST-API-Subscribe). Starter code: [Subscribe Recipes](Subscribe-Recipes).

## 7. Write tests

Swap the real Kit binding for a recording fake:

```php
use ArtisanPackUI\ConvertKit\Facades\ConvertKit;

$fake = ConvertKit::fake();

// ... exercise your code ...

$fake->assertSubscribed( 'jane@example.com' );
$fake->assertTagged( 'jane@example.com', 100 );
$fake->assertSentCount( 1 );
```

More: [Testing](Testing).

## Next steps

- [Installation](Installation) — full install walkthrough, publish tags, verifying the setup.
- [API Client](API-Client) — every endpoint the package wraps.
- [Forms Integration](Forms-Integration) — feeds, field mapping, conditional logic, the queued job.
- [REST API](REST-API) — feed admin CRUD, dry-run, and the public subscribe endpoint.
- [Events](Events) — everything the package dispatches so consumers can react.

---
Continue to [Installation](Installation) →
