---
title: Forms Integration
---

# Forms Integration

Pairs with [`artisanpack-ui/forms`](https://github.com/ArtisanPack-UI/forms). Flip on the integration and every submission of a form the [feed](Forms-Integration-Feeds) matches is evaluated, mapped to a Kit payload, and dispatched to a queue.

Sub-pages:

- [Feeds](Forms-Integration-Feeds)
- [Field Mapping](Forms-Integration-Field-Mapping)
- [Conditional Logic](Forms-Integration-Conditional-Logic)
- [Job Pipeline](Forms-Integration-Job-Pipeline)

## Enable

```dotenv
CONVERTKIT_FORMS_INTEGRATION=true
CONVERTKIT_QUEUE_CONNECTION=redis
CONVERTKIT_QUEUE=convertkit
```

The listener is subscribed at boot via string binding — the `FormSubmitted` event class only needs to exist at runtime, not at package boot. That means you can install this package before installing `artisanpack-ui/forms` and nothing blows up.

## How it works

1. **A user submits a form.** `artisanpack-ui/forms` stores the submission and dispatches `ArtisanPackUI\Forms\Events\FormSubmitted`.
2. **The `HandleFormSubmittedForKit` listener fires.** It bails immediately if the integration is disabled.
3. **The listener loads every active `KitFeed`** for the submitted form.
4. **The submission values are extracted once** (via `FieldMapper::extractValues()`).
5. **For each feed**, in order:
   - The [conditional logic](Forms-Integration-Conditional-Logic) is evaluated against the values. If it fails, `KitFeedSkipped` fires with `reason: 'conditional_logic'`.
   - The submission is [mapped](Forms-Integration-Field-Mapping) to a Kit payload. If mapping fails (missing email, etc.), `KitFeedSkipped` fires with `reason: 'field_map:...'`.
   - A [`ProcessKitFeed`](Forms-Integration-Job-Pipeline) job is dispatched with the mapped payload and the feed's `kit_tag_ids`.
6. **The worker executes the job.** On success, `KitSubscribed` fires. On terminal failure, `KitSubscriptionFailed` fires (see [Events](Events)).

One malformed feed cannot poison the loop — every failure path is caught per-feed so the rest of the feeds for the same submission still fire.

## Do I need a Kit form?

No. A `KitFeed` with `kit_form_id = null` uses the raw `subscribers()->create()` path and applies tags via follow-up `subscribers()->tag()` calls. A feed with `kit_form_id` set uses `forms()->subscribe()`, which is one API call for the subscribe + tag combo. Prefer setting a `kit_form_id` when you can — it's cheaper on Kit's rate limit.

## Where to configure

- Enable / disable: [`convertkit.forms_integration.enabled`](Installation-Configuration#forms_integration).
- Point at your form model: [`convertkit.forms_integration.form_model`](Installation-Configuration#forms_integration).
- Point at the event class: [`convertkit.forms_integration.form_submitted_event`](Installation-Configuration#forms_integration).
- Route Kit jobs to a dedicated queue: [`queue_connection` and `queue`](Installation-Configuration#forms_integration).

## What events to react to

- [`KitSubscribed`](Events#kitsubscribed) — a feed successfully subscribed an email.
- [`KitSubscriptionFailed`](Events#kitsubscriptionfailed) — a job failed terminally (auth, validation, or exhausted retries).
- [`KitFeedSkipped`](Events#kitfeedskipped) — a feed matched a submission but was skipped (conditional logic, missing email, etc.).

---
Continue to [Feeds](Forms-Integration-Feeds) →
