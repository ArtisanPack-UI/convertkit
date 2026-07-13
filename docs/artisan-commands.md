---
title: Artisan Commands
---

# Artisan Commands

Three Artisan commands ship with the package.

| Command | Purpose |
|---|---|
| `convertkit:test` | Verify the configured API key can reach Kit. |
| `convertkit:sync [resource]` | Force-refresh the cached forms, tags, and custom fields. |
| `convertkit:feeds <action> [id] [--form=]` | Manage [feeds](Forms-Integration-Feeds) from the CLI. |

Every command is safe to run in CI or during deploys — none of them prompt when the arguments are complete, and every failure path returns a non-zero exit code.

## `convertkit:test`

Pings `/account` and reports either success (with account name + plan) or one of three failure modes.

```bash
php artisan convertkit:test
```

Sample output:

```
Kit API is reachable.
  Account: Acme Newsletter
  Plan:    Creator Pro
```

Exit codes:

| Code | Meaning |
|---|---|
| `0` (`SUCCESS`) | API reachable. |
| `1` (`FAILURE`) | Auth failed, unreachable, or Kit returned an unexpected error. |
| `2` (`INVALID`) | No API key configured. |

Uses the current [HTTP client](Installation-Configuration) settings, so retries and timeout apply.

## `convertkit:sync [resource]`

Force-refresh the reference-data cache.

```bash
php artisan convertkit:sync            # all three
php artisan convertkit:sync forms
php artisan convertkit:sync tags
php artisan convertkit:sync fields
```

- Each call hits Kit once per resource and overwrites the cache.
- Reports the count refreshed: `Refreshed forms: 12 record(s).`
- Unknown resource name returns exit code `2` (`INVALID`).

Useful after:

- Publishing a new form in Kit's dashboard.
- Adding a new custom field you want the app to see immediately.
- Rotating your API key (the cache key includes the API key hash, so this isn't strictly required — but it's cleaner than waiting for the old TTL to expire).

## `convertkit:feeds <action> [id] [--form=]`

CLI feed management for teams that don't want to build a full admin UI yet.

### `list`

```bash
php artisan convertkit:feeds list
php artisan convertkit:feeds list --form=5
```

Prints a table of feeds. Columns: id, form id, name, kit form id, tag ids, active flag.

```
+----+------+-----------+----------+---------+--------+
| ID | Form | Name      | Kit Form | Tags    | Active |
+----+------+-----------+----------+---------+--------+
| 1  | 5    | Newsletter| 12345    | 100,200 | yes    |
| 2  | 5    | VIP       | 67890    | 300     | yes    |
+----+------+-----------+----------+---------+--------+
```

Empty list prints `No feeds found.` — still exit code `0`.

### `create`

```bash
php artisan convertkit:feeds create
```

Interactive wizard. Prompts for:

- Form id (integer)
- Feed name (string)
- Kit form id (optional — leave blank for a raw subscribe)
- Submission field slug holding the email address (default `email`)
- Comma-separated Kit tag ids (optional)

Creates a minimal feed with `field_map: { email_address: <slug> }` and `is_active: true`. To add more field mappings or conditional logic, use the [REST API](REST-API-Feed-Admin) or edit the row directly.

### `delete {id}`

```bash
php artisan convertkit:feeds delete 1
```

Asks for confirmation before deleting. Refuses to run without an id (exit code `2`). Prints "No feed with id N found." and returns exit code `1` if the id doesn't exist.

Deletion does not touch subscribers already at Kit — see [Feeds#deleting-a-feed](Forms-Integration-Feeds#deleting-a-feed).

### Unknown action

```
Unknown action 'foo'. Choose one of: list, create, delete.
```

Returns exit code `2`.

## Registration

All three commands are registered when the package boots in the console. No manual `Kernel` changes needed on Laravel 11+.

## Using in scheduled tasks

Only `convertkit:sync` really makes sense on a schedule — the others are one-shot admin ops. Example: refresh reference data hourly so a new tag added in Kit's dashboard propagates automatically.

```php
// routes/console.php (Laravel 11+)
use Illuminate\Support\Facades\Schedule;

Schedule::command( 'convertkit:sync' )->hourly()->withoutOverlapping();
```
