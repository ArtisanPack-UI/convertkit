<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Events\KitFeedSkipped;
use ArtisanPackUI\ConvertKit\Events\KitSubscribed;
use ArtisanPackUI\ConvertKit\Jobs\ProcessKitFeed;
use ArtisanPackUI\ConvertKit\Models\KitFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Stubs\FormSubmissionStub;
use Tests\Stubs\FormSubmittedStub;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    config()->set( 'convertkit.forms_integration.enabled', true );
} );

it( 'bails when forms integration is disabled', function (): void {
    config()->set( 'convertkit.forms_integration.enabled', false );

    Bus::fake();
    KitFeed::factory()->create( [ 'form_id' => 10 ] );

    FormSubmittedStub::dispatch( new FormSubmissionStub( 1, 10, [ 'email' => 'a@b.co' ] ) );

    Bus::assertNothingDispatched();
} );

it( 'dispatches a ProcessKitFeed job for each active matching feed', function (): void {
    Bus::fake();

    KitFeed::factory()->create( [
        'form_id'     => 10,
        'name'        => 'active',
        'field_map'   => [ 'email_address' => 'email' ],
        'kit_tag_ids' => [ 100 ],
        'is_active'   => true,
    ] );

    KitFeed::factory()->create( [
        'form_id'   => 10,
        'name'      => 'inactive',
        'field_map' => [ 'email_address' => 'email' ],
        'is_active' => false,
    ] );

    KitFeed::factory()->create( [
        'form_id'   => 99,
        'name'      => 'other-form',
        'field_map' => [ 'email_address' => 'email' ],
        'is_active' => true,
    ] );

    FormSubmittedStub::dispatch( new FormSubmissionStub( 42, 10, [ 'email' => 'a@b.co' ] ) );

    Bus::assertDispatchedTimes( ProcessKitFeed::class, 1 );
    Bus::assertDispatched( ProcessKitFeed::class, function ( ProcessKitFeed $job ): bool {
        return 'active' === $job->feed->name
            && 'a@b.co' === $job->payload['email_address']
            && [ 100 ] === $job->tagIds
            && 42 === $job->submissionId;
    } );
} );

it( 'skips a feed when conditional logic evaluates false', function (): void {
    Bus::fake();
    Event::fake( [ KitFeedSkipped::class ] );

    KitFeed::factory()->create( [
        'form_id'           => 10,
        'field_map'         => [ 'email_address' => 'email' ],
        'conditional_logic' => [
            'match'      => 'all',
            'conditions' => [
                [ 'field' => 'country', 'operator' => 'equals', 'value' => 'US' ],
            ],
        ],
    ] );

    FormSubmittedStub::dispatch( new FormSubmissionStub( 1, 10, [
        'email'   => 'a@b.co',
        'country' => 'CA',
    ] ) );

    Bus::assertNotDispatched( ProcessKitFeed::class );
    Event::assertDispatched( KitFeedSkipped::class, fn ( KitFeedSkipped $e ): bool => 'conditional_logic' === $e->reason );
} );

it( 'skips a feed when the field map cannot resolve an email', function (): void {
    Bus::fake();
    Event::fake( [ KitFeedSkipped::class ] );

    KitFeed::factory()->create( [
        'form_id'   => 10,
        'field_map' => [ 'email_address' => 'email' ],
    ] );

    FormSubmittedStub::dispatch( new FormSubmissionStub( 1, 10, [ 'name' => 'no email here' ] ) );

    Bus::assertNotDispatched( ProcessKitFeed::class );
    Event::assertDispatched( KitFeedSkipped::class );
} );

it( 'end-to-end via kit_form_id: subscribes with tags embedded and does NOT re-apply tags', function (): void {
    Event::fake( [ KitSubscribed::class ] );

    Http::fake( [
        'api.kit.com/v4/forms/12345/subscribers' => Http::response( [
            'subscriber' => [
                'id'            => 555,
                'email_address' => 'a@b.co',
                'state'         => 'active',
            ],
        ], 200 ),
    ] );

    KitFeed::factory()->create( [
        'form_id'     => 10,
        'kit_form_id' => 12345,
        'kit_tag_ids' => [ 100 ],
        'field_map'   => [ 'email_address' => 'email', 'first_name' => 'name' ],
    ] );

    FormSubmittedStub::dispatch( new FormSubmissionStub( 42, 10, [
        'email' => 'a@b.co',
        'name'  => 'Ada',
    ] ) );

    Http::assertSent( function ( $request ): bool {
        return str_ends_with( $request->url(), '/forms/12345/subscribers' )
            && 'a@b.co' === $request['email_address']
            && [ 100 ] === $request['tags'];
    } );

    // Tags were embedded in the subscribe payload above — we must NOT
    // hit /tags/{tag}/subscribers/{sub} a second time.
    Http::assertNotSent( fn ( $request ): bool => str_contains( $request->url(), '/tags/100/subscribers/' ) );
    Http::assertSentCount( 1 );

    Event::assertDispatched( KitSubscribed::class, fn ( KitSubscribed $e ): bool => 555 === $e->subscriber->id && 42 === $e->submissionId );
} );

it( 'end-to-end via subscribers()->create fallback: applies tags manually when kit_form_id is null', function (): void {
    Http::fake( [
        'api.kit.com/v4/subscribers' => Http::response( [
            'subscriber' => [
                'id'            => 777,
                'email_address' => 'a@b.co',
                'state'         => 'active',
            ],
        ], 200 ),
        'api.kit.com/v4/tags/100/subscribers/777' => Http::response( [], 200 ),
    ] );

    KitFeed::factory()->create( [
        'form_id'     => 10,
        'kit_form_id' => null,
        'kit_tag_ids' => [ 100 ],
        'field_map'   => [ 'email_address' => 'email' ],
    ] );

    FormSubmittedStub::dispatch( new FormSubmissionStub( 42, 10, [ 'email' => 'a@b.co' ] ) );

    Http::assertSent( fn ( $request ): bool => str_ends_with( $request->url(), '/v4/subscribers' ) );
    Http::assertSent( fn ( $request ): bool => str_ends_with( $request->url(), '/tags/100/subscribers/777' ) );
} );
