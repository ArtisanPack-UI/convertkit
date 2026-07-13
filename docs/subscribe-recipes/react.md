---
title: React Subscribe Form
---

# React Subscribe Form

Client-driven subscribe form that POSTs to [`/convertkit/subscribers`](REST-API-Subscribe).

## Fetch

Zero-dependency version. Copy into any React 18+ codebase.

```jsx
import { useState } from 'react';

const CSRF_TOKEN =
    document.querySelector( 'meta[name="csrf-token"]' )?.content ?? '';

export function SubscribeForm( { feedId } ) {
    const [ email, setEmail ] = useState( '' );
    const [ status, setStatus ] = useState( 'idle' );
    const [ error, setError ] = useState( null );

    async function submit( event ) {
        event.preventDefault();
        setStatus( 'sending' );
        setError( null );

        try {
            const response = await fetch( '/convertkit/subscribers', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                },
                body: JSON.stringify( { feed_id: feedId, email } ),
            } );

            if ( response.status === 202 ) {
                setStatus( 'done' );
                return;
            }

            if ( response.status === 429 ) {
                setError( 'Too many attempts — please try again in a minute.' );
                setStatus( 'idle' );
                return;
            }

            const body = await response.json().catch( () => ( {} ) );
            setError( body.message ?? 'Something went wrong.' );
            setStatus( 'idle' );
        } catch ( err ) {
            setError( 'Network error. Please try again.' );
            setStatus( 'idle' );
        }
    }

    if ( status === 'done' ) {
        return <p role="status">Thanks — check your inbox to confirm.</p>;
    }

    return (
        <form onSubmit={ submit } style={ { display: 'flex', gap: '.5rem' } }>
            <input
                type="email"
                required
                placeholder="you@example.com"
                value={ email }
                onChange={ ( e ) => setEmail( e.target.value ) }
                disabled={ status === 'sending' }
            />
            <button type="submit" disabled={ status === 'sending' }>
                { status === 'sending' ? 'Sending…' : 'Subscribe' }
            </button>
            { error && <p role="alert">{ error }</p> }
        </form>
    );
}
```

Usage:

```jsx
<SubscribeForm feedId={ 1 } />
```

## Axios

If you're already using axios and its interceptors handle CSRF for you (Laravel Breeze / Jetstream defaults), the request is a one-liner:

```jsx
import axios from 'axios';
import { useState } from 'react';

export function SubscribeForm( { feedId } ) {
    const [ email, setEmail ] = useState( '' );
    const [ status, setStatus ] = useState( 'idle' );
    const [ error, setError ] = useState( null );

    async function submit( event ) {
        event.preventDefault();
        setStatus( 'sending' );
        setError( null );

        try {
            await axios.post( '/convertkit/subscribers', { feed_id: feedId, email } );
            setStatus( 'done' );
        } catch ( err ) {
            if ( err.response?.status === 429 ) {
                setError( 'Too many attempts — please try again in a minute.' );
            } else {
                setError( err.response?.data?.message ?? 'Something went wrong.' );
            }
            setStatus( 'idle' );
        }
    }

    // …markup identical to the fetch example
}
```

## With `kit_form_id` instead

If you don't want the feed indirection:

```jsx
body: JSON.stringify( {
    kit_form_id: 12345,
    email,
    first_name: firstName,
    fields: { company },
    tags: [ 100 ],
} ),
```

Kit form ids are essentially public — embedding one in client-side JS is not a security concern.

## Validation errors

The endpoint returns `422` with Laravel's standard shape:

```json
{
    "message": "The email field must be a valid email address.",
    "errors": { "email": [ "The email field must be a valid email address." ] }
}
```

Handle it by iterating `errors` and showing the first message per field:

```jsx
if ( response.status === 422 ) {
    const body = await response.json();
    setFieldErrors( body.errors ?? {} );
    setStatus( 'idle' );
    return;
}
```

## CORS

If the SPA lives on a different origin from the Laravel app, add the subscribe endpoint's origin to `config/cors.php`, or add `HandleCors` to `convertkit.subscribe.middleware` in `config/convertkit.php`.

## Testing

Component-level: mock `fetch` (or axios) and assert the request shape.

End-to-end: use [Playwright](https://playwright.dev/) or Cypress against a real Laravel dev server with [`FakeConvertKit`](Testing) installed for the whole test run.
