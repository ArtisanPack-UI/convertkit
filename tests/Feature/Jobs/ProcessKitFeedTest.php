<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Api\Exceptions\KitAuthException;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitRateLimitException;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitServerException;
use ArtisanPackUI\ConvertKit\Events\KitSubscribed;
use ArtisanPackUI\ConvertKit\Events\KitSubscriptionFailed;
use ArtisanPackUI\ConvertKit\Jobs\ProcessKitFeed;
use ArtisanPackUI\ConvertKit\Models\KitFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses( RefreshDatabase::class );

it( 're-throws KitRateLimitException so the queue can retry', function (): void {
    Http::fake( [
        'api.kit.com/*' => Http::response( [ 'error' => 'rate' ], 429 ),
    ] );

    $feed = KitFeed::factory()->create( [ 'kit_form_id' => null, 'kit_tag_ids' => [] ] );

    $job = new ProcessKitFeed(
        feed         : $feed,
        payload      : [ 'email_address' => 'a@b.co' ],
        tagIds       : [],
        submissionId : 1,
    );

    expect( fn () => $job->handle( app( ArtisanPackUI\ConvertKit\ConvertKit::class ) ) )
        ->toThrow( KitRateLimitException::class );
} );

it( 're-throws KitServerException so the queue can retry', function (): void {
    Http::fake( [
        'api.kit.com/*' => Http::response( [ 'error' => 'boom' ], 503 ),
    ] );

    $feed = KitFeed::factory()->create( [ 'kit_form_id' => null, 'kit_tag_ids' => [] ] );

    $job = new ProcessKitFeed( $feed, [ 'email_address' => 'a@b.co' ], [], 1 );

    expect( fn () => $job->handle( app( ArtisanPackUI\ConvertKit\ConvertKit::class ) ) )
        ->toThrow( KitServerException::class );
} );

it( 'marks the job failed WITHOUT re-throwing on permanent errors like KitAuthException', function (): void {
    Http::fake( [
        'api.kit.com/*' => Http::response( [ 'error' => 'unauthorized' ], 401 ),
    ] );

    Event::fake( [ KitSubscriptionFailed::class ] );

    $feed = KitFeed::factory()->create( [ 'kit_form_id' => null, 'kit_tag_ids' => [] ] );

    $job = (new ProcessKitFeed( $feed, [ 'email_address' => 'a@b.co' ], [], 42 ))
        ->withFakeQueueInteractions();

    $job->handle( app( ArtisanPackUI\ConvertKit\ConvertKit::class ) );

    // Fake-queue interactions record `fail()` calls so we can assert
    // without hooking into a real queue driver.
    $job->assertFailedWith( KitAuthException::class );
} );

it( 'dispatches KitSubscriptionFailed exactly once from failed()', function (): void {
    Event::fake( [ KitSubscriptionFailed::class ] );

    $feed = KitFeed::factory()->create( [ 'kit_form_id' => null, 'kit_tag_ids' => [] ] );

    $job = new ProcessKitFeed( $feed, [ 'email_address' => 'a@b.co' ], [], 42 );

    $job->failed( new RuntimeException( 'boom' ) );

    Event::assertDispatchedTimes( KitSubscriptionFailed::class, 1 );
} );

it( 'does NOT re-apply tags when subscribing via a Kit form ID', function (): void {
    Http::fake( [
        'api.kit.com/v4/forms/12345/subscribers' => Http::response( [
            'subscriber' => [ 'id' => 555, 'email_address' => 'a@b.co', 'state' => 'active' ],
        ], 200 ),
    ] );

    Event::fake( [ KitSubscribed::class ] );

    $feed = KitFeed::factory()->create( [ 'kit_form_id' => 12345, 'kit_tag_ids' => [ 100, 200 ] ] );

    $job = new ProcessKitFeed( $feed, [ 'email_address' => 'a@b.co' ], [ 100, 200 ], 42 );
    $job->handle( app( ArtisanPackUI\ConvertKit\ConvertKit::class ) );

    Http::assertSentCount( 1 );
    Http::assertNotSent( fn ( $request ): bool => str_contains( $request->url(), '/tags/' ) );

    Event::assertDispatched( KitSubscribed::class );
} );
