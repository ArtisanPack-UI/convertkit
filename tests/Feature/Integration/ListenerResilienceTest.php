<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Events\KitFeedSkipped;
use ArtisanPackUI\ConvertKit\Jobs\ProcessKitFeed;
use ArtisanPackUI\ConvertKit\Models\KitFeed;
use ArtisanPackUI\ConvertKit\Support\ConditionalLogicEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\Stubs\FormSubmissionStub;
use Tests\Stubs\FormSubmittedStub;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    config()->set( 'convertkit.forms_integration.enabled', true );
} );

it( 'processes remaining feeds when one feed throws during evaluation', function (): void {
    Bus::fake();
    Event::fake( [ KitFeedSkipped::class ] );

    // Swap the evaluator with one that throws for the first feed (name
    // starts with 'poison') and behaves normally otherwise. This
    // simulates a stale operator, malformed rules, or any downstream
    // failure inside the evaluator — the outer loop must not abort.
    $this->app->bind( ConditionalLogicEvaluator::class, function () {
        return new class extends ConditionalLogicEvaluator {
            public bool $poisoned = false;

            public function evaluate( ?array $rules, array $submissionValues ): bool
            {
                if ( ! $this->poisoned ) {
                    $this->poisoned = true;
                    throw new RuntimeException( 'simulated evaluator explosion' );
                }

                return parent::evaluate( $rules, $submissionValues );
            }
        };
    } );

    KitFeed::factory()->create( [
        'form_id'   => 10,
        'name'      => 'poison',
        'field_map' => [ 'email_address' => 'email' ],
    ] );

    KitFeed::factory()->create( [
        'form_id'   => 10,
        'name'      => 'healthy',
        'field_map' => [ 'email_address' => 'email' ],
    ] );

    FormSubmittedStub::dispatch( new FormSubmissionStub( 1, 10, [ 'email' => 'a@b.co' ] ) );

    // Healthy feed still dispatches — one bad feed cannot poison the rest.
    Bus::assertDispatchedTimes( ProcessKitFeed::class, 1 );
    Bus::assertDispatched( ProcessKitFeed::class, fn ( ProcessKitFeed $job ): bool => 'healthy' === $job->feed->name );

    // Poison feed emits a skip event with an evaluator_error reason.
    Event::assertDispatched(
        KitFeedSkipped::class,
        fn ( KitFeedSkipped $e ): bool => 'poison' === $e->feed->name && str_starts_with( $e->reason, 'evaluator_error:' ),
    );
} );
