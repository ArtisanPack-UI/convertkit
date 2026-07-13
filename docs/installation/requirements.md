---
title: Requirements
---

# Requirements

## PHP

- **PHP 8.2 or newer.** The package uses constructor property promotion, readonly properties, and typed enums throughout.

## Laravel

- **Laravel 10.x, 11.x, 12.x, or 13.x.** The `illuminate/support` constraint is `^10.0|^11.0|^12.0|^13.0`.

The service provider auto-registers via package discovery.

## HTTP client

- **Laravel HTTP client** (`Illuminate\Http\Client\Factory`). Ships with every supported Laravel version. The package uses it directly rather than depending on Guzzle explicitly, so any Guzzle version Laravel bundles works fine.

## Required ArtisanPack packages

- **`artisanpack-ui/core` ^1.0** — pulled in transitively as a hard dependency.

## Optional peer packages

| Package | Version | What it enables |
|---|---|---|
| [`artisanpack-ui/forms`](https://github.com/ArtisanPack-UI/forms) | any | The [Forms Integration](Forms-Integration) — a `FormSubmitted` event triggers feed evaluation and dispatches Kit calls. The package looks the class up by string at runtime, so this can be added later without reinstalling. |
| [`artisanpack-ui/livewire-ui-components`](https://github.com/ArtisanPack-UI/livewire-ui-components) | any | Prettier Livewire subscribe forms in your app. The package itself ships no Livewire components. See [Livewire Recipe](Subscribe-Recipes-Livewire). |

Neither is required. The base package boots and works without them — the forms listener no-ops when the integration is disabled or when the event class does not exist.

## Database

The package ships one table:

- `convertkit_feeds` — one row per (form, Kit destination) join. Only used when the forms integration is enabled.

Any Laravel-supported connection works. Skip the migration entirely if you don't plan to use the forms integration.

## Kit API access

You need a **Kit v4 API key**. Generate one at [kit.com](https://kit.com/) under **Advanced → API**.

If you're upgrading from a Kit v3 project, note that this package targets v4 exclusively — v3 API keys and endpoints will not work. Kit's v4 introduced a new authentication scheme and payload shape; see [Kit's v4 migration guide](https://developers.kit.com/) for context.

## Queue driver (forms integration only)

The [`ProcessKitFeed`](Forms-Integration-Job-Pipeline) and [`SubscribeToKit`](REST-API-Subscribe#job-pipeline) jobs implement `ShouldQueue`. Any Laravel queue driver works — sync, database, redis, sqs, etc.

**Recommended**: use a persistent driver (`database`, `redis`, `sqs`) so a Kit outage doesn't drop submissions. The `sync` driver runs the API call inside the HTTP request, which trades throughput for immediacy.

## Cache store

Reference-data endpoints (forms, tags, custom fields) cache their `list()` results. Any Laravel cache store works. Configure via `CONVERTKIT_CACHE_STORE` — leave unset to use the app default.

## Session driver

Not used. The package's public endpoints are stateless.
