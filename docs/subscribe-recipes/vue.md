---
title: Vue Subscribe Form
---

# Vue Subscribe Form

Client-driven subscribe form that POSTs to [`/convertkit/subscribers`](REST-API-Subscribe). Vue 3, Composition API, `<script setup>`.

## Fetch

Zero-dependency version.

```vue
<script setup>
import { ref } from 'vue';

const props = defineProps( { feedId: { type: Number, required: true } } );

const CSRF_TOKEN =
    document.querySelector( 'meta[name="csrf-token"]' )?.content ?? '';

const email  = ref( '' );
const status = ref( 'idle' );
const error  = ref( null );

async function submit() {
    status.value = 'sending';
    error.value  = null;

    try {
        const response = await fetch( '/convertkit/subscribers', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
            },
            body: JSON.stringify( { feed_id: props.feedId, email: email.value } ),
        } );

        if ( response.status === 202 ) {
            status.value = 'done';
            return;
        }

        if ( response.status === 429 ) {
            error.value  = 'Too many attempts — please try again in a minute.';
            status.value = 'idle';
            return;
        }

        const body = await response.json().catch( () => ( {} ) );
        error.value  = body.message ?? 'Something went wrong.';
        status.value = 'idle';
    } catch {
        error.value  = 'Network error. Please try again.';
        status.value = 'idle';
    }
}
</script>

<template>
    <p v-if="status === 'done'" role="status">
        Thanks — check your inbox to confirm.
    </p>

    <form v-else @submit.prevent="submit" style="display: flex; gap: .5rem">
        <input
            v-model="email"
            type="email"
            placeholder="you@example.com"
            required
            :disabled="status === 'sending'"
        />
        <button type="submit" :disabled="status === 'sending'">
            {{ status === 'sending' ? 'Sending…' : 'Subscribe' }}
        </button>
    </form>

    <p v-if="error" role="alert">{{ error }}</p>
</template>
```

Usage:

```vue
<SubscribeForm :feed-id="1" />
```

## Axios

If axios interceptors already handle CSRF for you (Breeze / Jetstream defaults):

```vue
<script setup>
import axios from 'axios';
import { ref } from 'vue';

const props = defineProps( { feedId: { type: Number, required: true } } );

const email  = ref( '' );
const status = ref( 'idle' );
const error  = ref( null );

async function submit() {
    status.value = 'sending';
    error.value  = null;

    try {
        await axios.post( '/convertkit/subscribers', {
            feed_id: props.feedId,
            email:   email.value,
        } );
        status.value = 'done';
    } catch ( err ) {
        if ( err.response?.status === 429 ) {
            error.value = 'Too many attempts — please try again in a minute.';
        } else {
            error.value = err.response?.data?.message ?? 'Something went wrong.';
        }
        status.value = 'idle';
    }
}
</script>
```

Template same as the fetch version.

## With `kit_form_id` instead

Skip the feed indirection:

```js
body: JSON.stringify( {
    kit_form_id: 12345,
    email: email.value,
    first_name: firstName.value,
    fields: { company: company.value },
    tags: [ 100 ],
} ),
```

## Validation errors

Same shape as the React recipe — Laravel returns `422` with `{ message, errors }`. Handle by mapping `errors` to per-field state:

```js
if ( response.status === 422 ) {
    const body = await response.json();
    fieldErrors.value = body.errors ?? {};
    status.value = 'idle';
    return;
}
```

## Emit success

If the form is a child of a parent that owns the success UI:

```vue
<script setup>
const emit = defineEmits( [ 'subscribed' ] );

// …inside the 202 branch:
emit( 'subscribed', email.value );
</script>
```

## CORS

If the Vue SPA lives on a different origin from Laravel, add the subscribe endpoint's origin to `config/cors.php`, or add `HandleCors` to `convertkit.subscribe.middleware`.

## Testing

Component-level: [Vitest](https://vitest.dev/) + [Vue Test Utils](https://test-utils.vuejs.org/), mock `fetch` (or axios) and assert the request shape.

End-to-end: [Playwright](https://playwright.dev/) or Cypress with [`FakeConvertKit`](Testing) installed on the Laravel side for the whole test run.
