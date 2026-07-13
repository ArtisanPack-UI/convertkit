---
title: Conditional Logic
---

# Conditional Logic

A feed's optional `conditional_logic` decides whether the feed fires for a given submission. `null` (or an empty object) means "always fire" — most feeds don't need conditions.

## Structure

```json
{
    "match": "all",
    "conditions": [
        { "field": "plan", "operator": "equals", "value": "pro" },
        { "field": "email", "operator": "contains", "value": "@" }
    ]
}
```

- `match` — `"all"` (AND) or `"any"` (OR). Defaults to `"all"` if omitted.
- `conditions` — an array of `{ field, operator, value }` objects.

Each condition compares the submission's value for `field` (using the submission's slug, not the Kit destination) against `value` using `operator`.

## Operators

| Operator | Meaning |
|---|---|
| `equals` | Exact string match after normalization. |
| `not_equals` | Inverse of `equals`. |
| `contains` | Substring check on the actual value. For array values, checks each item. |
| `not_contains` | Inverse of `contains`. |
| `is_empty` | Actual value is `null`, empty string (after `trim`), or empty array. |
| `is_not_empty` | Inverse of `is_empty`. |

The full canonical list also lives on `ConditionalLogicEvaluator::OPERATORS` — used by the [feed store request](REST-API-Feed-Admin) to reject unknown operators at validation time.

## Value normalization

Both sides of an equality comparison are normalized to strings before comparing:

- `null` → `""`
- `true` → `"1"`, `false` → `"0"`
- Scalars → `(string) $value`
- Arrays/objects → `json_encode()`

This lets you write `{"field": "opt_in", "operator": "equals", "value": "1"}` regardless of whether the submission stores the checkbox as `true`, `"1"`, or `1`.

For `contains` and `not_contains`, if the actual value is an array, each item is checked (both exact and substring). This handles multi-select fields cleanly.

## Semantics

- `match: "all"` — every condition must pass. If any condition throws or is malformed, the feed does not fire.
- `match: "any"` — at least one condition must pass. Malformed conditions are treated as failing.
- Missing operator or field → the condition is treated as failing.

Missing conditions arrays or an empty conditions list → the feed fires (nothing to reject it).

## Examples

### Only fire for pro plans

```json
{
    "match": "all",
    "conditions": [
        { "field": "plan", "operator": "equals", "value": "pro" }
    ]
}
```

### Fire for anyone who selected either "marketing" or "sales" as their role

```json
{
    "match": "any",
    "conditions": [
        { "field": "role", "operator": "equals", "value": "marketing" },
        { "field": "role", "operator": "equals", "value": "sales" }
    ]
}
```

### Skip when the honeypot field was filled

```json
{
    "match": "all",
    "conditions": [
        { "field": "hp_url", "operator": "is_empty" }
    ]
}
```

### Only fire for @acme.com email addresses

```json
{
    "match": "all",
    "conditions": [
        { "field": "email", "operator": "contains", "value": "@acme.com" }
    ]
}
```

## Testing your rules

Dry-run the feed against representative submissions:

```
POST /admin/convertkit/feeds/{id}/test
{ "values": { "email": "x@y.co", "plan": "pro" } }
```

- `{ "would_send": true, "reason": null, "payload": {...} }` — the rules passed.
- `{ "would_send": false, "reason": "conditional_logic", "payload": null }` — the rules failed.

See [Feed Dry-Run](REST-API-Dry-Run).

## Direct usage

Instantiate the evaluator directly if you want to reuse the same rule engine elsewhere:

```php
use ArtisanPackUI\ConvertKit\Support\ConditionalLogicEvaluator;

$evaluator = app( ConditionalLogicEvaluator::class );

$passes = $evaluator->evaluate( $rules, $submissionValues );
```

Pure and dependency-free — no side effects, no HTTP calls.

## Errors thrown from a rule

If a bad condition throws unexpectedly, the listener catches it and emits `KitFeedSkipped` with `reason: 'evaluator_error:...'`. This lets ops trace which feed misfired without silently losing the submission for other feeds.
