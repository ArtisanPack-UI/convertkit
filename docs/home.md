---
title: Home
---

# ArtisanPack UI ConvertKit Documentation

Welcome to the documentation for **ArtisanPack UI ConvertKit** — a Laravel integration for the [Kit](https://kit.com/) (formerly ConvertKit) v4 API. The package ships a fluent API client, a feed-driven bridge to [`artisanpack-ui/forms`](https://github.com/ArtisanPack-UI/forms), public REST endpoints for subscribe forms in any front-end, Artisan commands for reference-data sync and feed management, and a `FakeConvertKit` test double for consumer apps.

Use the navigation below to jump between topics. Links use the GitLab wiki page style — [Getting Started](Getting-Started), [REST API](REST-API), and so on.

- [Getting Started](Getting-Started)
- [Installation](Installation)
- [API Client](API-Client)
- [Forms Integration](Forms-Integration)
- [REST API](REST-API)
- [Subscribe Recipes](Subscribe-Recipes)
- [Artisan Commands](Artisan-Commands)
- [Events](Events)
- [Errors](Errors)
- [Testing](Testing)
- [FAQ](FAQ)
- [Contributing](Contributing)

If you're new here, start with [Getting Started](Getting-Started).

## What this package does

`artisanpack-ui/convertkit` owns Kit integration for Laravel apps. It provides:

- **Kit v4 HTTP client** with exponential-backoff retries, `Retry-After` handling, and typed exceptions for auth, rate limit, validation, not-found, and server errors.
- **Four endpoint wrappers** — subscribers, forms, tags, and custom fields — returning immutable DTOs.
- **Reference-data cache** for forms, tags, and custom fields (rarely change) with a `convertkit:sync` command to force-refresh.
- **Forms integration** that listens for `ArtisanPackUI\Forms\Events\FormSubmitted`, evaluates a per-form [`KitFeed`](Forms-Integration-Feeds), maps the submission to a Kit payload, and dispatches a queued job.
- **REST endpoints** for feed CRUD, feed dry-runs, and a public subscribe endpoint front-end forms can POST to.
- **`FakeConvertKit`** — a recording test double that swaps the container binding, so consumer app tests can assert against Kit calls without touching the network.

## What this package does not do

- It does **not** ship UI. Feed admin panels, subscribe forms, and status pages are all left to the consumer app (Livewire, React, Vue, Blade, etc.). See [Subscribe Recipes](Subscribe-Recipes) for starter code.
- It does **not** poll Kit for state changes. Read-heavy service pages should query the cached endpoint lists; write-heavy work happens on submit.
- It does **not** perform double-opt-in confirmation on its own — that's a Kit-side setting on the target form. The package just POSTs the subscribe.
