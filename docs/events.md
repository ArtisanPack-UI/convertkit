---
title: Events
---

# Events

Three events fire during normal package operation. All live in `ArtisanPackUI\ConvertKit\Events`.

Register listeners the standard Laravel way — the event provider, an `Event::listen()` call in a service provider, or the `#[AsListener]` attribute on a class method.

## `KitSubscribed`

Fired when a subscribe call to Kit succeeds.

```php
namespace ArtisanPackUI\ConvertKit\Events;

class KitSubscribed
{
    public function __construct(
        public readonly KitFeed $feed,
        public readonly Subscriber $subscriber,
        public readonly array $payload,
        public readonly int $submissionId,
    ) {}
}
```

**When it fires:**

- Inside `ProcessKitFeed::handle()` immediately after Kit accepts the subscribe (forms-integration path).
- Inside `SubscribeToKit::handle()` — **not currently dispatched** because the public subscribe endpoint doesn't carry a feed / submission context in every case. If you need a success signal from the public path, wrap the job or subscribe to the queue's `JobProcessed` event.

**Common uses:**

- Write an audit row: "Jane's submission #123 subscribed her via feed 'Newsletter' to Kit form 12345 at 2026-07-13T…".
- Fire an app-specific "welcome" flow separate from Kit's own broadcast.
- Update a UI badge showing "N subscribers this week".

## `KitSubscriptionFailed`

Fired when a Kit subscribe fails **terminally** — retries exhausted or a non-retryable error.

```php
namespace ArtisanPackUI\ConvertKit\Events;

class KitSubscriptionFailed
{
    public function __construct(
        public readonly KitFeed $feed,
        public readonly array $payload,
        public readonly int $submissionId,
        public readonly Throwable $exception,
    ) {}
}
```

**When it fires:**

- From `ProcessKitFeed::failed()`, which fires **exactly once** per terminal failure. The job's `handle()` deliberately does not dispatch this event inline on catch — retrying `KitRateLimitException` / `KitServerException` would otherwise emit duplicate events.

**Common uses:**

- Alert on validation errors (bad `kit_form_id`, unknown custom-field key) so an ops team can fix the feed config.
- Alert on auth errors (rotated API key not yet propagated to prod).
- Store the failed payload for manual replay from an admin UI.

The `$exception` is one of the [`KitException`](Errors) subclasses; check its class to route by failure kind.

## `KitFeedSkipped`

Fired when a feed matches a submission but is deliberately skipped — the listener never dispatches a job for the feed.

```php
namespace ArtisanPackUI\ConvertKit\Events;

class KitFeedSkipped
{
    public function __construct(
        public readonly KitFeed $feed,
        public readonly int $submissionId,
        public readonly string $reason,
    ) {}
}
```

**Reason values:**

| Reason prefix | Meaning |
|---|---|
| `conditional_logic` | The feed's [conditional logic](Forms-Integration-Conditional-Logic) evaluated to `false`. |
| `field_map:<message>` | The [field mapper](Forms-Integration-Field-Mapping) threw. The `<message>` portion is `FieldMapperException::getMessage()`. |
| `evaluator_error:<message>` | The conditional evaluator itself threw unexpectedly. The `<message>` is the raw exception message. |
| `dispatch_error:<message>` | Job dispatch itself failed (queue driver misconfigured, etc.). |

**Common uses:**

- Debug: log skips per feed per submission, so you can trace why a submission you expected to go to Kit didn't.
- Analytics: count how often each feed's conditional logic rejects vs. accepts.
- Alerting: if the reason starts with `evaluator_error:` or `dispatch_error:` that's usually a bug worth paging on.

Note: **inactive feeds do not fire this event.** The listener skips them at query time before evaluation. Only feeds that were loaded and then rejected get a `KitFeedSkipped`.

## Listening

```php
namespace App\Providers;

use ArtisanPackUI\ConvertKit\Events\KitSubscribed;
use ArtisanPackUI\ConvertKit\Events\KitSubscriptionFailed;
use App\Listeners\WriteConvertKitAuditRow;
use App\Listeners\AlertOnConvertKitFailure;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen( KitSubscribed::class, WriteConvertKitAuditRow::class );
        Event::listen( KitSubscriptionFailed::class, AlertOnConvertKitFailure::class );
    }
}
```

## Testing that events fire

```php
use Illuminate\Support\Facades\Event;

Event::fake();

// … dispatch the form submitted event, or drive the queue …

Event::assertDispatched( KitSubscribed::class, fn ( $event ): bool =>
    $event->subscriber->email === 'jane@example.com'
);
```

For higher-level assertions against Kit itself, use [`FakeConvertKit`](Testing) — it records the API calls; the events still fire under the fake.
