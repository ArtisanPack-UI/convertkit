---
title: Subscribe Recipes
---

# Subscribe Recipes

Starter code for building a subscribe form on top of the [public subscribe endpoint](REST-API-Subscribe). Every recipe posts to `/convertkit/subscribers` — no Kit credentials touch the client.

Sub-pages by stack:

- [Livewire](Subscribe-Recipes-Livewire) — server-driven, no bundler required.
- [React](Subscribe-Recipes-React) — client-driven, fetch or axios.
- [Vue](Subscribe-Recipes-Vue) — client-driven, `<script setup>`.

## Which one should I use?

- **Livewire** — you already have Livewire, you want the same shape as your other forms, and you don't want to add a JS bundle just for a subscribe field.
- **React / Vue** — the target page is a static or CDN-served asset, or your front-end is fully client-side, or you want to embed the form on a marketing page that doesn't have Laravel session cookies.

The endpoint itself doesn't care which one you pick.

## What the recipes have in common

Every recipe:

1. Renders an email input and a submit button.
2. On submit, POSTs to `/convertkit/subscribers` with either `feed_id` or `kit_form_id`.
3. Shows one of three states: idle, sending, done (or errored).
4. Handles the `422` case gracefully (surfaces the Laravel validation errors) and the `429` case (shows a "try again in a minute" hint).

## Common failure modes

- **CSRF for same-origin Livewire forms.** Livewire handles this transparently. React / Vue recipes include an `X-CSRF-TOKEN` header from the `<meta name="csrf-token">` tag Laravel emits.
- **Cross-origin POSTs from an SPA on a different domain.** You'll need to either sit the SPA behind the same origin, add CORS via `config/cors.php`, or expose a token-authenticated variant of the endpoint. The [public subscribe endpoint config](Installation-Configuration#subscribe) lets you add middleware — a `HandleCors` middleware entry is a clean fit.
- **Kit form is set to double opt-in.** Kit sends the confirmation email; the immediate 202 response does not mean the subscriber is confirmed. That's a UX consideration for your success state ("Check your inbox to confirm.").
- **Rate limit hit.** Default 10/min per IP. On shared / NAT'd IPs (offices, mobile carriers) a burst of legitimate traffic can hit this. Tune via [config](Installation-Configuration#subscribe).

---
Continue to [Livewire](Subscribe-Recipes-Livewire) →
