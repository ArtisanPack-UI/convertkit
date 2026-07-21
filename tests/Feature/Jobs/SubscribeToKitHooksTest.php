<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Api\Endpoints\SubscribersEndpoint;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitRateLimitException;
use ArtisanPackUI\ConvertKit\Facades\ConvertKit;
use ArtisanPackUI\ConvertKit\Jobs\SubscribeToKit;
use ArtisanPackUI\ConvertKit\Testing\FakeConvertKit;
use ArtisanPackUI\ConvertKit\Testing\FakeSubscribersEndpoint;

/**
 * Endpoint that throws a configured exception from `create()`. Used to
 * exercise the failure branches in {@see SubscribeToKit::handle()}.
 */
class ThrowingSubscribersEndpoint extends FakeSubscribersEndpoint
{
    public function __construct( FakeConvertKit $fake, protected Throwable $exception )
    {
        parent::__construct( $fake );
    }

    public function create( string $email, ?string $firstName = null, array $fields = [] ): ArtisanPackUI\ConvertKit\Api\DTOs\Subscriber
    {
        throw $this->exception;
    }
}

/**
 * FakeConvertKit that swaps the subscribers endpoint for one that throws.
 */
class ThrowingFakeConvertKit extends FakeConvertKit
{
    protected Throwable $exception;

    public function __construct( Throwable $exception )
    {
        parent::__construct();
        $this->exception = $exception;
    }

    public function subscribers(): SubscribersEndpoint
    {
        return new ThrowingSubscribersEndpoint( $this, $this->exception );
    }
}

/**
 * Hooks fired by {@see SubscribeToKit}: `ap.convertkit.subscribing`
 * (before the Kit API call), `ap.convertkit.subscribed` (after a
 * successful subscribe), and `ap.convertkit.subscribeFailed` (on any
 * Kit-side error).
 *
 * These are the audit / analytics / downstream-sync seam consumers hang
 * PII scrubbing, CRM propagation, and observability off of without
 * patching the job.
 */

afterEach( function (): void {
    removeAllActions( 'ap.convertkit.subscribing' );
    removeAllActions( 'ap.convertkit.subscribed' );
    removeAllActions( 'ap.convertkit.subscribeFailed' );
} );

it( 'fires ap.convertkit.subscribing before the API call with the input attributes', function (): void {
    $capturedEmail      = null;
    $capturedAttributes = null;

    addAction(
        'ap.convertkit.subscribing',
        function ( string $email, array $attributes ) use ( &$capturedEmail, &$capturedAttributes ): void {
            $capturedEmail      = $email;
            $capturedAttributes = $attributes;
        },
    );

    $fake = ConvertKit::fake();

    ( new SubscribeToKit(
        email: 'jane@example.com',
        firstName: 'Jane',
        fields: [ 'company' => 'Acme' ],
        tagIds: [ 10, 20 ],
        kitFormId: 555,
    ) )->handle( $fake );

    expect( $capturedEmail )->toBe( 'jane@example.com' );
    expect( $capturedAttributes )->toBe( [
        'first_name'  => 'Jane',
        'fields'      => [ 'company' => 'Acme' ],
        'tag_ids'     => [ 10, 20 ],
        'kit_form_id' => 555,
    ] );
} );

it( 'reports capped tag_ids in the subscribing attributes when the input exceeds MAX_TAGS_PER_JOB', function (): void {
    $capturedAttributes = null;

    addAction(
        'ap.convertkit.subscribing',
        function ( string $email, array $attributes ) use ( &$capturedAttributes ): void {
            $capturedAttributes = $attributes;
        },
    );

    $fake = ConvertKit::fake();

    ( new SubscribeToKit(
        email: 'a@b.co',
        firstName: null,
        fields: [],
        tagIds: range( 1, 200 ),
        kitFormId: null,
    ) )->handle( $fake );

    expect( count( $capturedAttributes['tag_ids'] ) )->toBe( SubscribeToKit::MAX_TAGS_PER_JOB );
    expect( $capturedAttributes['tag_ids'][0] )->toBe( 1 );
    expect( end( $capturedAttributes['tag_ids'] ) )->toBe( SubscribeToKit::MAX_TAGS_PER_JOB );
} );

it( 'fires ap.convertkit.subscribed after a successful forms-endpoint subscribe', function (): void {
    $capturedEmail    = null;
    $capturedResponse = null;

    addAction(
        'ap.convertkit.subscribed',
        function ( string $email, array $response ) use ( &$capturedEmail, &$capturedResponse ): void {
            $capturedEmail    = $email;
            $capturedResponse = $response;
        },
    );

    $fake = ConvertKit::fake();

    ( new SubscribeToKit(
        email: 'a@b.co',
        firstName: null,
        fields: [],
        tagIds: [ 1 ],
        kitFormId: 555,
    ) )->handle( $fake );

    expect( $capturedEmail )->toBe( 'a@b.co' );
    expect( $capturedResponse )->toMatchArray( [
        'email' => 'a@b.co',
        'state' => 'active',
    ] );
    expect( $capturedResponse['id'] )->toBeInt();
} );

it( 'fires ap.convertkit.subscribed after a successful raw subscribers-endpoint create', function (): void {
    $capturedResponse = null;

    addAction(
        'ap.convertkit.subscribed',
        function ( string $email, array $response ) use ( &$capturedResponse ): void {
            $capturedResponse = $response;
        },
    );

    $fake = ConvertKit::fake();

    ( new SubscribeToKit(
        email: 'jane@example.com',
        firstName: 'Jane',
        fields: [ 'company' => 'Acme' ],
        tagIds: [],
        kitFormId: null,
    ) )->handle( $fake );

    expect( $capturedResponse )->toMatchArray( [
        'email'      => 'jane@example.com',
        'first_name' => 'Jane',
        'fields'     => [ 'company' => 'Acme' ],
    ] );
} );

it( 'fires ap.convertkit.subscribeFailed on retryable transient failures and re-throws', function (): void {
    $capturedEmail     = null;
    $capturedException = null;

    addAction(
        'ap.convertkit.subscribeFailed',
        function ( string $email, Throwable $exception ) use ( &$capturedEmail, &$capturedException ): void {
            $capturedEmail     = $email;
            $capturedException = $exception;
        },
    );

    $fake = new ThrowingFakeConvertKit( new KitRateLimitException( 'rate limited', 429 ) );

    $job = new SubscribeToKit(
        email: 'boom@example.com',
        firstName: null,
        fields: [],
        tagIds: [],
        kitFormId: null,
    );

    expect( fn () => $job->handle( $fake ) )->toThrow( KitRateLimitException::class );

    expect( $capturedEmail )->toBe( 'boom@example.com' );
    expect( $capturedException )->toBeInstanceOf( KitRateLimitException::class );
} );

it( 'fires ap.convertkit.subscribeFailed on terminal failures and does not re-throw', function (): void {
    $capturedException = null;

    addAction(
        'ap.convertkit.subscribeFailed',
        function ( string $email, Throwable $exception ) use ( &$capturedException ): void {
            $capturedException = $exception;
        },
    );

    $fake = new ThrowingFakeConvertKit( new RuntimeException( 'invalid api key' ) );

    $job = (new SubscribeToKit(
        email: 'boom@example.com',
        firstName: null,
        fields: [],
        tagIds: [],
        kitFormId: null,
    ))->withFakeQueueInteractions();

    $job->handle( $fake );

    expect( $capturedException )->toBeInstanceOf( RuntimeException::class );
    expect( $capturedException->getMessage() )->toBe( 'invalid api key' );
    $job->assertFailedWith( RuntimeException::class );
} );

it( 'does not fire the subscribed hook when the subscribe call fails', function (): void {
    $subscribedFired = false;

    addAction(
        'ap.convertkit.subscribed',
        function () use ( &$subscribedFired ): void {
            $subscribedFired = true;
        },
    );

    $fake = new ThrowingFakeConvertKit( new RuntimeException( 'nope' ) );

    $job = (new SubscribeToKit(
        email: 'boom@example.com',
        firstName: null,
        fields: [],
        tagIds: [],
        kitFormId: null,
    ))->withFakeQueueInteractions();

    $job->handle( $fake );

    expect( $subscribedFired )->toBeFalse();
} );
