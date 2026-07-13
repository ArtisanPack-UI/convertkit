<?php

/**
 * Listener that fans a form submission out to matching Kit feeds.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Listeners;

use ArtisanPackUI\ConvertKit\Events\KitFeedSkipped;
use ArtisanPackUI\ConvertKit\Jobs\ProcessKitFeed;
use ArtisanPackUI\ConvertKit\Models\KitFeed;
use ArtisanPackUI\ConvertKit\Support\ConditionalLogicEvaluator;
use ArtisanPackUI\ConvertKit\Support\FieldMapper;
use ArtisanPackUI\ConvertKit\Support\FieldMapperException;
use Throwable;

/**
 * Loads active feeds for the submitted form, evaluates each one against
 * the submission, and dispatches a `ProcessKitFeed` job per surviving
 * feed. Feeds that fail conditional logic or whose field map cannot
 * resolve an email dispatch a `KitFeedSkipped` event and never touch
 * the network.
 *
 * The listener is subscribed via string binding in the service provider
 * so `\ArtisanPackUI\Forms\Events\FormSubmitted` only needs to exist at
 * runtime — not at package boot.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class HandleFormSubmittedForKit
{
    public function __construct(
        protected ConditionalLogicEvaluator $evaluator,
        protected FieldMapper $mapper,
    ) {
    }

    public function handle( object $event ): void
    {
        if ( ! (bool) config( 'convertkit.forms_integration.enabled' ) ) {
            return;
        }

        $submission = $event->submission ?? null;

        if ( ! is_object( $submission ) ) {
            return;
        }

        $formId       = $this->extractInt( $submission, 'form_id' );
        $submissionId = $this->extractInt( $submission, 'id' ) ?? 0;

        if ( null === $formId ) {
            return;
        }

        $feeds = KitFeed::query()
            ->where( 'form_id', $formId )
            ->where( 'is_active', true )
            ->get();

        // Extract submission values ONCE and pass the pure array to
        // `mapper->mapValues()` per feed — otherwise `mapper->map()`
        // re-runs the same duck-typed walk on every iteration.
        $values = $this->mapper->extractValues( $submission );

        foreach ( $feeds as $feed ) {
            $this->processFeed( $feed, $values, $submissionId );
        }
    }

    /**
     * Per-feed evaluation, mapping, and dispatch. Every branch that can
     * throw is caught here so one malformed feed cannot poison the rest
     * of the loop in `handle()`. Unexpected throws emit a `KitFeedSkipped`
     * event with a `reason` prefixed by the failing stage, giving ops a
     * breadcrumb without silently losing the submission for other feeds.
     *
     * @param  array<string, mixed>  $values
     */
    protected function processFeed( KitFeed $feed, array $values, int $submissionId ): void
    {
        try {
            if ( ! $this->evaluator->evaluate( $feed->conditional_logic, $values ) ) {
                KitFeedSkipped::dispatch( $feed, $submissionId, 'conditional_logic' );

                return;
            }
        } catch ( Throwable $e ) {
            KitFeedSkipped::dispatch( $feed, $submissionId, 'evaluator_error:' . $e->getMessage() );

            return;
        }

        try {
            $payload = $this->mapper->mapValues( $values, is_array( $feed->field_map ) ? $feed->field_map : [] );
        } catch ( FieldMapperException $e ) {
            KitFeedSkipped::dispatch( $feed, $submissionId, 'field_map:' . $e->getMessage() );

            return;
        }

        try {
            ProcessKitFeed::dispatch(
                $feed,
                $payload,
                is_array( $feed->kit_tag_ids ) ? $feed->kit_tag_ids : [],
                $submissionId,
            );
        } catch ( Throwable $e ) {
            KitFeedSkipped::dispatch( $feed, $submissionId, 'dispatch_error:' . $e->getMessage() );
        }
    }

    protected function extractInt( object $submission, string $key ): ?int
    {
        $value = $submission->{ $key } ?? null;

        if ( is_numeric( $value ) ) {
            return (int) $value;
        }

        return null;
    }
}
