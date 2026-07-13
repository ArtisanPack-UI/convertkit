---
title: Livewire Subscribe Form
---

# Livewire Subscribe Form

Server-driven subscribe form using Livewire 3.

Two flavors:

- **Direct** — call the Kit API from the Livewire component itself. Simpler; no queue involvement.
- **Via the public endpoint** — dispatch the queued job via `POST /convertkit/subscribers`. Better under load.

## Direct

Bypasses the REST endpoint entirely. Straight into the [API client](API-Client).

```php
namespace App\Livewire;

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
                    <p class="text-success">Thanks — check your inbox to confirm.</p>
                @else
                    <form wire:submit="submit" class="flex gap-2">
                        <input
                            type="email"
                            wire:model="email"
                            placeholder="you@example.com"
                            class="input input-bordered flex-1"
                            required
                        />
                        <button
                            type="submit"
                            class="btn btn-primary"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove>Subscribe</span>
                            <span wire:loading>Sending…</span>
                        </button>
                    </form>
                    @error( 'email' )
                        <p class="mt-2 text-error">{{ $message }}</p>
                    @enderror
                @endif
            </div>
        BLADE;
    }
}
```

Drop it in a Blade view:

```blade
<livewire:subscribe-form />
```

**Trade-off.** The API call runs inside the HTTP request. If Kit is slow, your form is slow. Fine for low-volume forms; not great for a homepage hero.

## Via the public endpoint (queued)

Same UX, but POSTs to `/convertkit/subscribers` so the Kit call runs on the queue.

```php
namespace App\Livewire;

use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Validate;
use Livewire\Component;

class SubscribeForm extends Component
{
    #[Validate( 'required|email' )]
    public string $email = '';

    public bool $done = false;

    public ?string $error = null;

    public function submit(): void
    {
        $this->validate();
        $this->error = null;

        $response = Http::acceptJson()
            ->asJson()
            ->post( url( '/convertkit/subscribers' ), [
                'feed_id' => 1,
                'email'   => $this->email,
            ] );

        if ( 202 === $response->status() ) {
            $this->done = true;

            return;
        }

        if ( 429 === $response->status() ) {
            $this->error = 'Too many attempts — please try again in a minute.';

            return;
        }

        $this->error = $response->json( 'message', 'Something went wrong. Try again shortly.' );
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                @if ( $done )
                    <p class="text-success">Thanks — check your inbox to confirm.</p>
                @else
                    <form wire:submit="submit" class="flex gap-2">
                        <input
                            type="email"
                            wire:model="email"
                            placeholder="you@example.com"
                            class="input input-bordered flex-1"
                            required
                        />
                        <button
                            type="submit"
                            class="btn btn-primary"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove>Subscribe</span>
                            <span wire:loading>Sending…</span>
                        </button>
                    </form>
                    @if ( $error )
                        <p class="mt-2 text-error">{{ $error }}</p>
                    @endif
                    @error( 'email' )
                        <p class="mt-2 text-error">{{ $message }}</p>
                    @enderror
                @endif
            </div>
        BLADE;
    }
}
```

Since Livewire runs server-side, CSRF and session cookies are handled transparently — no manual token wiring.

## With `artisanpack-ui/livewire-ui-components`

Swap the raw markup for the shipped components:

```blade
<div>
    @if ( $done )
        <x-artisanpack-alert type="success">
            Thanks — check your inbox to confirm.
        </x-artisanpack-alert>
    @else
        <form wire:submit="submit" class="flex gap-2">
            <x-artisanpack-input
                type="email"
                wire:model="email"
                placeholder="you@example.com"
                :error="$errors->first('email')"
                required
            />
            <x-artisanpack-button color="primary" type="submit" wire:loading.attr="disabled">
                Subscribe
            </x-artisanpack-button>
        </form>
    @endif
</div>
```

## Testing the component

Use [`FakeConvertKit`](Testing) plus Livewire's testing helpers.

```php
use ArtisanPackUI\ConvertKit\Facades\ConvertKit;
use App\Livewire\SubscribeForm;
use Livewire\Livewire;

it( 'subscribes via the API client', function (): void {
    $fake = ConvertKit::fake();

    Livewire::test( SubscribeForm::class )
        ->set( 'email', 'jane@example.com' )
        ->call( 'submit' )
        ->assertSet( 'done', true );

    $fake->assertSubscribed( 'jane@example.com', 12345 );
} );
```

For the queued variant, fake the HTTP client:

```php
use Illuminate\Support\Facades\Http;

Http::fake( [ 'convertkit/subscribers' => Http::response( [ 'message' => 'Subscribe queued.' ], 202 ) ] );
```
