---
title: Testing
---

# Testing

The package ships `FakeConvertKit` — a recording test double that swaps the container binding so consumer app tests can drive Kit calls without touching the network.

## Install the fake

```php
use ArtisanPackUI\ConvertKit\Facades\ConvertKit;

$fake = ConvertKit::fake();
```

`ConvertKit::fake()` binds a fresh `FakeConvertKit` against both:

- The `'convertkit'` container alias (so the facade + `convertkit()` helper return the fake).
- The concrete `ArtisanPackUI\ConvertKit\ConvertKit::class` binding (so constructor-injected consumers — including the [`ProcessKitFeed`](Forms-Integration-Job-Pipeline) and [`SubscribeToKit`](REST-API-Subscribe#job-pipeline) jobs — resolve the fake too).

Call it once at the top of the test (or in `beforeEach`). It returns the fake so you can drive assertions.

## Assertions

### `assertSubscribed( string $email, ?int $formId = null ): void`

Assert that an email was subscribed. Passing `$formId` also requires that the subscribe went through that specific Kit form (via `forms()->subscribe()`); leave it null to match any subscribe path.

```php
$fake->assertSubscribed( 'jane@example.com' );
$fake->assertSubscribed( 'jane@example.com', 12345 );
```

### `assertTagged( string $email, int $tagId ): void`

Assert that a tag was applied to a subscribed email. Matches either a standalone `subscribers()->tag()` call **or** a `forms()->subscribe()` that carried the tag in its payload.

```php
$fake->assertTagged( 'jane@example.com', 100 );
```

### `assertNothingSent(): void`

Assert that no subscribe, tag, or untag calls were recorded.

```php
$fake->assertNothingSent();
```

### `assertSentCount( int $count ): void`

Assert an exact number of subscribe calls.

```php
$fake->assertSentCount( 1 );
```

Counts only distinct subscribe calls — tag applies do not increment.

## Public recording arrays

For custom assertions the built-in helpers don't cover:

- `$fake->subscribed` — every subscribe call: `[ [ 'email', 'first_name', 'fields', 'form_id', 'tags' ], … ]`.
- `$fake->tagged` — every standalone `tag()` call: `[ [ 'email', 'tag_id' ], … ]`.
- `$fake->untagged` — every `untag()` call: `[ [ 'email', 'tag_id' ], … ]`.

```php
expect( $fake->subscribed )->toHaveCount( 3 );
expect( $fake->subscribed[0]['fields'] )->toBe( [ 'company' => 'Acme' ] );
```

## Return values

Every fake endpoint method returns a plausible-looking `Subscriber` DTO so downstream code that uses the return value doesn't have to be different in tests:

- `subscribers()->create()` and `forms()->subscribe()` return a `Subscriber` with a fresh incrementing id (`1, 2, 3, …`), `state = 'active'`, and the fields you passed in.
- `subscribers()->find(id)` returns a `Subscriber` with that id and empty email.
- `subscribers()->findByEmail(email)` returns a matching subscriber if you recorded one via `create`; otherwise `null`.
- `subscribers()->unsubscribe(id)` returns a `Subscriber` with `state = 'cancelled'`.
- List endpoints (`list()`, `refresh()`) return `[]`.

## Example — a controller test

```php
use ArtisanPackUI\ConvertKit\Facades\ConvertKit;

it( 'subscribes users on signup', function (): void {
    $fake = ConvertKit::fake();

    $this->post( '/signup', [
        'email' => 'jane@example.com',
        'name'  => 'Jane',
    ] )->assertRedirect( '/dashboard' );

    $fake->assertSubscribed( 'jane@example.com' );
    $fake->assertTagged( 'jane@example.com', 100 );
    $fake->assertSentCount( 1 );
} );
```

## Example — a queued job under the fake

Since `ConvertKit::fake()` also binds the concrete class, any queued job that type-hints `ConvertKit` in its `handle()` will run against the fake even if you're processing the queue synchronously.

```php
use ArtisanPackUI\ConvertKit\Facades\ConvertKit;
use ArtisanPackUI\ConvertKit\Jobs\SubscribeToKit;

it( 'the queued job hits Kit', function (): void {
    $fake = ConvertKit::fake();

    ( new SubscribeToKit(
        email: 'a@b.co',
        firstName: null,
        fields: [],
        tagIds: [ 1, 2 ],
        kitFormId: 555,
    ) )->handle( $fake );

    $fake->assertSubscribed( 'a@b.co', 555 );
    $fake->assertTagged( 'a@b.co', 1 );
    $fake->assertTagged( 'a@b.co', 2 );
} );
```

## Testing the forms integration end-to-end

Flip the integration on inside the test, fake ConvertKit, then dispatch the forms event manually.

```php
use ArtisanPackUI\ConvertKit\Facades\ConvertKit;
use ArtisanPackUI\ConvertKit\Models\KitFeed;

it( 'fires a feed when the form is submitted', function (): void {
    config()->set( 'convertkit.forms_integration.enabled', true );

    $feed = KitFeed::factory()->create( [
        'kit_form_id' => 555,
        'kit_tag_ids' => [ 100 ],
        'field_map'   => [ 'email_address' => 'email' ],
    ] );

    $fake = ConvertKit::fake();

    // Dispatch a stand-in for the forms event.
    event( new \Tests\Stubs\FormSubmittedStub(
        submission: (object) [ 'id' => 1, 'form_id' => $feed->form_id, 'data_array' => [ 'email' => 'a@b.co' ] ],
    ) );

    // With sync queue, the job ran inline; assert against the fake.
    $fake->assertSubscribed( 'a@b.co', 555 );
    $fake->assertTagged( 'a@b.co', 100 );
} );
```

Set `QUEUE_CONNECTION=sync` in the test env so `dispatch()` runs the job in-process.

## Faking events instead

If you don't care about the Kit call itself — only that an event fired — use Laravel's `Event::fake()`:

```php
use Illuminate\Support\Facades\Event;
use ArtisanPackUI\ConvertKit\Events\KitSubscribed;

Event::fake( [ KitSubscribed::class ] );

// … drive the code …

Event::assertDispatched( KitSubscribed::class );
```

You can combine both — the fake fires events under the hood, and `Event::fake` will observe them.
