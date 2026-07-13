<?php

/**
 * ConvertKit (Kit) package configuration.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

return [

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | Your Kit (formerly ConvertKit) v4 API key. Generate one in your Kit
    | account settings under "Advanced" → "API".
    |
    */

    'api_key' => env( 'CONVERTKIT_API_KEY' ),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    |
    | Low-level HTTP settings for talking to the Kit v4 API.
    |
    | - base_url: Kit v4 API base URL.
    | - timeout: Per-request timeout in seconds.
    | - retries: How many times to retry a request that hits a 429 or 5xx.
    | - retry_delay: Base delay between retries in milliseconds. The client
    |   uses exponential backoff and respects the `Retry-After` header.
    |
    */

    'base_url' => env( 'CONVERTKIT_BASE_URL', 'https://api.kit.com/v4' ),

    'timeout' => (int) env( 'CONVERTKIT_TIMEOUT', 15 ),

    'retries' => (int) env( 'CONVERTKIT_RETRIES', 3 ),

    'retry_delay' => (int) env( 'CONVERTKIT_RETRY_DELAY', 500 ),

    /*
    | Upper bound for a single retry wait (milliseconds). Prevents exponential
    | backoff from stretching into multi-minute waits when retries is set high.
    */

    'max_backoff' => (int) env( 'CONVERTKIT_MAX_BACKOFF', 30_000 ),

    /*
    | Upper bound for honoring a `Retry-After` header from Kit (seconds). If
    | Kit asks us to wait longer than this, the client surfaces a
    | `KitRateLimitException` immediately rather than pinning the process.
    */

    'max_retry_after' => (int) env( 'CONVERTKIT_MAX_RETRY_AFTER', 60 ),

    /*
    | Refuse to send the API key if `base_url` is not https:// unless this is
    | explicitly enabled. Keep this false in production.
    */

    'allow_insecure_http' => (bool) env( 'CONVERTKIT_ALLOW_INSECURE_HTTP', false ),

    /*
    |--------------------------------------------------------------------------
    | Reference Data Cache
    |--------------------------------------------------------------------------
    |
    | Forms, tags, and custom fields change rarely, so their `list()` calls
    | are cached. Configure the cache store and per-endpoint TTL (seconds).
    | Use the `convertkit:sync` Artisan command to force a refresh.
    |
    */

    'cache' => [
        'store'      => env( 'CONVERTKIT_CACHE_STORE' ),
        'prefix'     => 'convertkit',
        'forms_ttl'  => (int) env( 'CONVERTKIT_FORMS_TTL', 3600 ),
        'tags_ttl'   => (int) env( 'CONVERTKIT_TAGS_TTL', 3600 ),
        'fields_ttl' => (int) env( 'CONVERTKIT_FIELDS_TTL', 3600 ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Forms Package Integration
    |--------------------------------------------------------------------------
    |
    | Wires this package to `artisanpack-ui/forms` so a `FormSubmitted` event
    | triggers evaluation of `KitFeed`s and dispatches queued Kit calls. All
    | coupling is config-driven so the forms package remains an optional peer.
    |
    | - enabled: master switch. When false, no listener is registered and no
    |   feeds fire. Keep off if the forms package is not installed.
    | - form_model: FQCN of the forms package Form model. Used for the
    |   `KitFeed::form()` relationship and by feed validation.
    | - form_submitted_event: FQCN of the event dispatched by the forms
    |   package when a submission is stored. Subscribed via string binding
    |   so the class only needs to exist at runtime, not at package boot.
    | - queue_connection / queue: optional overrides for the ProcessKitFeed
    |   job so ops can route Kit work to a dedicated queue.
    |
    */

    'forms_integration' => [
        'enabled'              => (bool) env( 'CONVERTKIT_FORMS_INTEGRATION', false ),
        'form_model'           => env( 'CONVERTKIT_FORMS_MODEL', '\\ArtisanPackUI\\Forms\\Models\\Form' ),
        'form_submitted_event' => env( 'CONVERTKIT_FORM_SUBMITTED_EVENT', '\\ArtisanPackUI\\Forms\\Events\\FormSubmitted' ),
        'queue_connection'     => env( 'CONVERTKIT_QUEUE_CONNECTION' ),
        'queue'                => env( 'CONVERTKIT_QUEUE' ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feed Admin REST API
    |--------------------------------------------------------------------------
    |
    | Configuration for the feed CRUD endpoints. This package does not ship a
    | UI — devs build one in Livewire/React/Vue on top of these endpoints.
    |
    | - route_prefix: URL prefix under which feed routes are mounted.
    | - middleware: middleware stack applied to feed routes.
    | - gate_ability: Gate ability checked before every feed action. Define
    |   your own `Gate::define()` for this ability in your app.
    |
    */

    'feed_admin' => [
        'route_prefix' => env( 'CONVERTKIT_FEED_ADMIN_PREFIX', 'admin/convertkit' ),
        'middleware'   => [ 'web', 'auth' ],
        'gate_ability' => env( 'CONVERTKIT_FEED_ADMIN_ABILITY', 'manage-convertkit-feeds' ),
    ],

];
