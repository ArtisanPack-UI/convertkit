<?php

/**
 * Queued job that fires a single Kit feed for a submission.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Jobs;

use ArtisanPackUI\ConvertKit\Api\DTOs\Subscriber;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitException;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitRateLimitException;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitServerException;
use ArtisanPackUI\ConvertKit\ConvertKit;
use ArtisanPackUI\ConvertKit\Events\KitSubscribed;
use ArtisanPackUI\ConvertKit\Events\KitSubscriptionFailed;
use ArtisanPackUI\ConvertKit\Models\KitFeed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Sends a mapped payload to Kit for one feed / submission pair.
 *
 * Retry policy is narrow on purpose: only `KitRateLimitException` and
 * `KitServerException` (5xx) are re-thrown so the queue worker retries
 * them. Every other error — auth (rotated key), validation (bad
 * kit_form_id), 4xx not found — is a permanent failure that would burn
 * quota if retried, so we call `$this->fail($e)` to mark the job dead
 * immediately. `KitSubscriptionFailed` is dispatched only from
 * `failed()` so subscribers see one event per terminal failure.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class ProcessKitFeed implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  array<string, mixed>  $payload  The mapped Kit payload
     *                                         (`email_address`, optional
     *                                         `first_name`, optional
     *                                         `fields`).
     * @param  array<int, int|string>  $tagIds  Kit tag IDs to apply after
     *                                          subscription.
     */
    public function __construct(
        public readonly KitFeed $feed,
        public readonly array $payload,
        public readonly array $tagIds,
        public readonly int $submissionId,
    ) {
        $connection = config( 'convertkit.forms_integration.queue_connection' );

        if ( is_string( $connection ) && '' !== $connection ) {
            $this->onConnection( $connection );
        }

        $queue = config( 'convertkit.forms_integration.queue' );

        if ( is_string( $queue ) && '' !== $queue ) {
            $this->onQueue( $queue );
        }
    }

    public function handle( ConvertKit $convertKit ): void
    {
        try {
            $subscriber = $this->subscribe( $convertKit );

            // When we hit `forms()->subscribe()` the tags were already
            // embedded in the subscribe payload — Kit applied them
            // server-side, so re-applying via `subscribers()->tag()`
            // would double the API cost and trigger duplicate
            // tag_applied webhooks. Only the `subscribers()->create()`
            // fallback path needs the manual apply loop.
            if ( null === $this->feed->kit_form_id ) {
                foreach ( $this->tagIds as $tagId ) {
                    if ( is_numeric( $tagId ) ) {
                        $convertKit->subscribers()->tag( $subscriber->id, (int) $tagId );
                    }
                }
            }

            KitSubscribed::dispatch( $this->feed, $subscriber, $this->payload, $this->submissionId );
        } catch ( KitRateLimitException | KitServerException $e ) {
            // Transient — re-throw so the queue worker retries us.
            throw $e;
        } catch ( Throwable $e ) {
            // Permanent — mark the job failed immediately so it does
            // not retry and burn quota. `failed()` will dispatch
            // `KitSubscriptionFailed` exactly once.
            $this->fail( $e );
        }
    }

    public function failed( Throwable $exception ): void
    {
        KitSubscriptionFailed::dispatch( $this->feed, $this->payload, $this->submissionId, $exception );
    }

    protected function subscribe( ConvertKit $convertKit ): Subscriber
    {
        $email     = (string) ( $this->payload['email_address'] ?? '' );
        $firstName = isset( $this->payload['first_name'] ) ? (string) $this->payload['first_name'] : null;
        $fields    = is_array( $this->payload['fields'] ?? null ) ? $this->payload['fields'] : [];

        if ( '' === $email ) {
            throw new KitException( 'ProcessKitFeed received a payload with no email address.', 0 );
        }

        if ( null !== $this->feed->kit_form_id ) {
            return $convertKit->forms()->subscribe(
                $this->feed->kit_form_id,
                $email,
                $fields,
                $this->tagIds,
            );
        }

        return $convertKit->subscribers()->create( $email, $firstName, $fields );
    }
}
