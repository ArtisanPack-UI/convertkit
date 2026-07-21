# ArtisanPack UI ConvertKit

ConvertKit (Kit) integration for Laravel. Ships a Kit v4 API client, a
feed-driven `artisanpack-ui/forms` bridge, public REST endpoints for
subscribe forms in any front-end, Artisan commands for reference-data
sync and feed management, and a `FakeConvertKit` test double for
consumer apps.

- Laravel 10, 11, 12, and 13
- PHP 8.2+
- Kit v4 API

## Installation

```bash
composer require artisanpack-ui/convertkit
```

Publish the config and run migrations:

```bash
php artisan vendor:publish --tag=convertkit-config
php artisan vendor:publish --tag=convertkit-migrations
php artisan migrate
```

## Configuration

Set your Kit v4 API key in `.env`:

```dotenv
CONVERTKIT_API_KEY=your-kit-v4-api-key
```

Generate a key in your Kit account under **Advanced → API**. Verify the
key can reach Kit:

```bash
php artisan convertkit:test
```

Full config options live in `config/convertkit.php` after publishing —
retries, cache TTLs, forms-integration toggles, rate-limit windows, etc.

## Basic Usage

### Subscribers

```php
use ArtisanPackUI\ConvertKit\Facades\ConvertKit;

// Create a subscriber
$subscriber = ConvertKit::subscribers()->create(
    email: 'jane@example.com',
    firstName: 'Jane',
    fields: [ 'company' => 'Acme' ],
);

// Find by email
$existing = ConvertKit::subscribers()->findByEmail( 'jane@example.com' );

// Apply / remove a tag
ConvertKit::subscribers()->tag( $subscriber->id, 12345 );
ConvertKit::subscribers()->untag( $subscriber->id, 12345 );

// Unsubscribe
ConvertKit::subscribers()->unsubscribe( $subscriber->id );
```

The `convertkit()` helper is equivalent to the facade:

```php
convertkit()->subscribers()->create( 'jane@example.com' );
```

### Forms, Tags, Custom Fields

```php
$forms  = ConvertKit::forms()->list();          // cached; use ->refresh() to force
$tags   = ConvertKit::tags()->list();
$fields = ConvertKit::customFields()->list();

// Subscribe to a specific Kit form (applies tags server-side)
ConvertKit::forms()->subscribe(
    formId: 12345,
    email: 'jane@example.com',
    fields: [ 'company' => 'Acme' ],
    tags: [ 10, 20 ],
);
```

Refresh the cached reference data:

```bash
php artisan convertkit:sync            # all
php artisan convertkit:sync forms
php artisan convertkit:sync tags
php artisan convertkit:sync fields
```

## Forms Integration

Pairs with [`artisanpack-ui/forms`](https://gitlab.com/jacob-martella-web-design/artisanpack-ui/forms).
Flip on the integration and every submission of a form the feed matches
is evaluated, mapped to a Kit payload, and dispatched to a queue.

```dotenv
CONVERTKIT_FORMS_INTEGRATION=true
CONVERTKIT_QUEUE_CONNECTION=redis
CONVERTKIT_QUEUE=convertkit
```

### Creating a feed

Use the REST endpoints (see below) or the CLI wizard:

```bash
php artisan convertkit:feeds create
```

A feed has:

- `form_id` — the `artisanpack-ui/forms` form to listen on
- `kit_form_id` — the Kit form to subscribe to (optional; leave null for
  a raw `subscribers()->create()` subscribe)
- `kit_tag_ids` — Kit tag ids to apply on subscribe
- `field_map` — Kit destination → submission field slug
- `conditional_logic` — optional rule set that must pass before the feed
  fires

### Field mapping

Keys are Kit destinations, values are your submission's field slugs:

```json
{
    "email_address": "email",
    "first_name": "name",
    "company": "company_name"
}
```

`email_address` and `first_name` land at the top of the Kit payload;
anything else (like `company`) becomes a Kit custom-field entry.

### Conditional logic

```json
{
    "match": "all",
    "conditions": [
        { "field": "plan", "operator": "equals", "value": "pro" },
        { "field": "email", "operator": "contains", "value": "@" }
    ]
}
```

Supported operators: `equals`, `not_equals`, `contains`, `not_contains`,
`is_empty`, `is_not_empty`. `match` is `all` (AND) or `any` (OR).

## REST API

All feed-admin routes sit under `admin/convertkit` by default and are
guarded by the `manage-convertkit-feeds` Gate ability. Define your own
gate closure:

```php
use ArtisanPackUI\ConvertKit\Models\KitFeed;

Gate::define(
    'manage-convertkit-feeds',
    fn ( User $user, ?KitFeed $feed = null ): bool => $user->isAdmin(),
);
```

### Feeds

| Method   | Path                                       | Purpose                                          |
|----------|--------------------------------------------|--------------------------------------------------|
| `GET`    | `/admin/convertkit/feeds`                  | List feeds. Filter with `?form_id=`.             |
| `POST`   | `/admin/convertkit/feeds`                  | Create a feed.                                   |
| `GET`    | `/admin/convertkit/feeds/{id}`             | Show a feed.                                     |
| `PUT`    | `/admin/convertkit/feeds/{id}`             | Update a feed.                                   |
| `DELETE` | `/admin/convertkit/feeds/{id}`             | Delete a feed.                                   |
| `POST`   | `/admin/convertkit/feeds/{id}/test`        | Dry-run a feed against a sample submission.      |

Example — dry-run:

```bash
curl -X POST https://example.test/admin/convertkit/feeds/1/test \
    -H 'Content-Type: application/json' \
    -H 'Accept: application/json' \
    -d '{"values": {"email": "jane@example.com", "plan": "pro"}}'
```

Response:

```json
{
    "would_send": true,
    "reason": null,
    "payload": {
        "email_address": "jane@example.com",
        "fields": { "plan": "pro" }
    }
}
```

When conditional logic blocks the feed, `would_send` is `false` and
`reason` is `"conditional_logic"`. When the field map can't resolve an
email, `reason` starts with `field_map:`.

### Public subscribe endpoint

The public endpoint is what front-end forms POST to. Actual Kit calls
run on the queue, so it always returns `202 Accepted`.

`POST /convertkit/subscribers`

Either pass a `feed_id` (uses that feed's Kit form + tags) or a bare
`kit_form_id`:

```json
{
    "feed_id": 1,
    "email": "jane@example.com",
    "first_name": "Jane",
    "tags": [ 100 ]
}
```

```json
{
    "kit_form_id": 12345,
    "email": "jane@example.com",
    "fields": { "company": "Acme" }
}
```

Rate limited to 10 attempts per IP per minute by default. Tune via
`CONVERTKIT_SUBSCRIBE_MAX_ATTEMPTS` and `CONVERTKIT_SUBSCRIBE_DECAY_MINUTES`.

## Recipes

### Livewire subscribe form

```php
use Livewire\Attributes\Validate;
use Livewire\Component;

class SubscribeForm extends Component
{
    #[Validate( 'required|email' )]
    public string $email = '';

    public bool $done = false;

    public function submit(): void
    {
        $this->validate();

        convertkit()->forms()->subscribe(
            formId: 12345,
            email: $this->email,
            tags: [ 100 ],
        );

        $this->done = true;
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                @if ( $done )
                    <p>Thanks — check your inbox.</p>
                @else
                    <form wire:submit="submit">
                        <input type="email" wire:model="email" required />
                        <button type="submit" wire:loading.attr="disabled">
                            Subscribe
                        </button>
                    </form>
                @endif
            </div>
        BLADE;
    }
}
```

### React subscribe form

```jsx
import { useState } from 'react';

export function SubscribeForm() {
    const [ email, setEmail ] = useState( '' );
    const [ status, setStatus ] = useState( 'idle' );

    async function submit( event ) {
        event.preventDefault();
        setStatus( 'sending' );

        const response = await fetch( '/convertkit/subscribers', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document
                    .querySelector( 'meta[name="csrf-token"]' )
                    ?.content ?? '',
            },
            body: JSON.stringify( { feed_id: 1, email } ),
        } );

        setStatus( response.ok ? 'done' : 'error' );
    }

    if ( status === 'done' ) {
        return <p>Thanks — check your inbox.</p>;
    }

    return (
        <form onSubmit={ submit }>
            <input
                type="email"
                required
                value={ email }
                onChange={ ( e ) => setEmail( e.target.value ) }
            />
            <button type="submit" disabled={ status === 'sending' }>
                Subscribe
            </button>
        </form>
    );
}
```

### Vue subscribe form

```vue
<script setup>
import { ref } from 'vue';

const email = ref( '' );
const status = ref( 'idle' );

async function submit() {
    status.value = 'sending';

    const response = await fetch( '/convertkit/subscribers', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN':
                document.querySelector( 'meta[name="csrf-token"]' )?.content ?? '',
        },
        body: JSON.stringify( { feed_id: 1, email: email.value } ),
    } );

    status.value = response.ok ? 'done' : 'error';
}
</script>

<template>
    <p v-if="status === 'done'">Thanks — check your inbox.</p>
    <form v-else @submit.prevent="submit">
        <input v-model="email" type="email" required />
        <button type="submit" :disabled="status === 'sending'">Subscribe</button>
    </form>
</template>
```

## Artisan Commands

| Command                              | Purpose                                        |
|--------------------------------------|------------------------------------------------|
| `convertkit:test`                    | Verify the configured API key can reach Kit.   |
| `convertkit:sync [resource]`         | Refresh cached forms/tags/fields.              |
| `convertkit:feeds list [--form=]`    | Table of feeds, optionally filtered by form.   |
| `convertkit:feeds create`            | Interactive wizard for creating a feed.        |
| `convertkit:feeds delete {id}`       | Delete a feed (with confirmation).             |

## Testing

Swap the real ConvertKit binding for a recording fake:

```php
use ArtisanPackUI\ConvertKit\Facades\ConvertKit;

it( 'subscribes users to the newsletter', function (): void {
    $fake = ConvertKit::fake();

    $this->post( '/signup', [ 'email' => 'jane@example.com' ] )
        ->assertRedirect();

    $fake->assertSubscribed( 'jane@example.com' );
    $fake->assertTagged( 'jane@example.com', 100 );
    $fake->assertSentCount( 1 );
} );
```

Available assertions:

- `assertSubscribed( string $email, ?int $formId = null )`
- `assertTagged( string $email, int $tagId )`
- `assertNothingSent()`
- `assertSentCount( int $count )`

The fake never touches the network, so tests stay hermetic.

## Hooks

The `SubscribeToKit` job fires three ArtisanPack UI hooks around every
subscribe attempt — direct-form and raw-subscribers paths both flow
through them. Register listeners with `addAction()` (from
`artisanpack-ui/hooks`).

| Hook                             | When                                 | Signature                                    |
|----------------------------------|--------------------------------------|----------------------------------------------|
| `ap.convertkit.subscribing`      | Immediately before the Kit API call. | `(string $email, array $attributes)`         |
| `ap.convertkit.subscribed`       | After a successful subscribe.        | `(string $email, array $response)`           |
| `ap.convertkit.subscribeFailed`  | On any subscribe exception.          | `(string $email, Throwable $exception)`      |

`$attributes` is the payload map about to be sent to Kit:
`first_name`, `fields`, `tag_ids` (already capped at
`SubscribeToKit::MAX_TAGS_PER_JOB = 50`), and `kit_form_id`.

`$response` is the Kit subscriber decoded to an array: `id`, `email`,
`state`, `first_name`, `created_at`, `fields`.

`subscribeFailed` fires per `handle()` invocation, so a retryable
transient failure (rate limit, 5xx) that the queue will retry emits one
hook per attempt. Inspect the exception type to distinguish transient
from terminal.

```php
addAction( 'ap.convertkit.subscribed', function ( string $email, array $response ): void {
    Log::info( 'Kit subscribed', [ 'email' => $email, 'subscriber_id' => $response['id'] ] );
} );
```

## Contributing

As an open source project, this package is open to contributions from
anyone. Please [read through the contributing guidelines](CONTRIBUTING.md)
to learn more about how you can contribute to this project.
