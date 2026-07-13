---
title: Environment Variables
---

# Environment Variables

Every env var the package reads, with defaults and cross-links to the config key that consumes it. Full config reference: [Configuration](Installation-Configuration).

## HTTP client

| Env var | Default | Config key |
|---|---|---|
| `CONVERTKIT_API_KEY` | *(none)* | `convertkit.api_key` |
| `CONVERTKIT_BASE_URL` | `https://api.kit.com/v4` | `convertkit.base_url` |
| `CONVERTKIT_TIMEOUT` | `15` | `convertkit.timeout` |
| `CONVERTKIT_RETRIES` | `3` | `convertkit.retries` |
| `CONVERTKIT_RETRY_DELAY` | `500` | `convertkit.retry_delay` |
| `CONVERTKIT_MAX_BACKOFF` | `30000` | `convertkit.max_backoff` |
| `CONVERTKIT_MAX_RETRY_AFTER` | `60` | `convertkit.max_retry_after` |
| `CONVERTKIT_ALLOW_INSECURE_HTTP` | `false` | `convertkit.allow_insecure_http` |

## Reference-data cache

| Env var | Default | Config key |
|---|---|---|
| `CONVERTKIT_CACHE_STORE` | *(app default)* | `convertkit.cache.store` |
| `CONVERTKIT_FORMS_TTL` | `3600` | `convertkit.cache.forms_ttl` |
| `CONVERTKIT_TAGS_TTL` | `3600` | `convertkit.cache.tags_ttl` |
| `CONVERTKIT_FIELDS_TTL` | `3600` | `convertkit.cache.fields_ttl` |

## Forms integration

| Env var | Default | Config key |
|---|---|---|
| `CONVERTKIT_FORMS_INTEGRATION` | `false` | `convertkit.forms_integration.enabled` |
| `CONVERTKIT_FORMS_MODEL` | `\ArtisanPackUI\Forms\Models\Form` | `convertkit.forms_integration.form_model` |
| `CONVERTKIT_FORM_SUBMITTED_EVENT` | `\ArtisanPackUI\Forms\Events\FormSubmitted` | `convertkit.forms_integration.form_submitted_event` |
| `CONVERTKIT_QUEUE_CONNECTION` | *(default connection)* | `convertkit.forms_integration.queue_connection` |
| `CONVERTKIT_QUEUE` | *(default queue)* | `convertkit.forms_integration.queue` |

## Feed admin REST API

| Env var | Default | Config key |
|---|---|---|
| `CONVERTKIT_FEED_ADMIN_PREFIX` | `admin/convertkit` | `convertkit.feed_admin.route_prefix` |
| `CONVERTKIT_FEED_ADMIN_ABILITY` | `manage-convertkit-feeds` | `convertkit.feed_admin.gate_ability` |

Middleware is set in the config file directly, not via env.

## Public subscribe endpoint

| Env var | Default | Config key |
|---|---|---|
| `CONVERTKIT_SUBSCRIBE_PREFIX` | `convertkit` | `convertkit.subscribe.route_prefix` |
| `CONVERTKIT_SUBSCRIBE_MAX_ATTEMPTS` | `10` | `convertkit.subscribe.throttle.max_attempts` |
| `CONVERTKIT_SUBSCRIBE_DECAY_MINUTES` | `1` | `convertkit.subscribe.throttle.decay_minutes` |

Middleware is set in the config file directly, not via env.

## Recommended `.env` block

```dotenv
# Kit v4 API key. Get one at kit.com under Advanced → API.
CONVERTKIT_API_KEY=your-kit-v4-api-key

# Forms integration — enable only if artisanpack-ui/forms is installed.
CONVERTKIT_FORMS_INTEGRATION=false
CONVERTKIT_QUEUE_CONNECTION=redis
CONVERTKIT_QUEUE=convertkit

# Feed admin (leave defaults unless customizing).
CONVERTKIT_FEED_ADMIN_ABILITY=manage-convertkit-feeds

# Public subscribe endpoint rate limit.
CONVERTKIT_SUBSCRIBE_MAX_ATTEMPTS=10
CONVERTKIT_SUBSCRIBE_DECAY_MINUTES=1
```
