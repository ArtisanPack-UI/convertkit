<?php

/**
 * Queued job that subscribes an email address to Kit.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Jobs;

use ArtisanPackUI\ConvertKit\Api\Exceptions\KitRateLimitException;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitServerException;
use ArtisanPackUI\ConvertKit\ConvertKit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Dispatched by the public subscribe REST endpoint. Handles both:
 *
 *   - direct `kit_form_id` subscribes via `forms()->subscribe()` (tags
 *     ride along on the payload)
 *   - "raw" subscribes via `subscribers()->create()` (tags applied in
 *     a follow-up loop)
 *
 * Retries only on transient failures (rate limit, 5xx) — every other
 * error is a permanent misconfiguration and marked failed immediately
 * so we don't burn Kit quota retrying doomed requests.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class SubscribeToKit implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Defense-in-depth cap on how many tags a single job will apply. The
     * public request path already validates `tags` with `max:20`, but
     * this bound also protects future dispatchers (admin flows,
     * integrations) from unbounded fan-out to the Kit API.
     */
    public const MAX_TAGS_PER_JOB = 50;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  array<string, mixed>  $fields  Kit-shaped custom-field payload.
     * @param  array<int, int>  $tagIds  Kit tag IDs to apply after subscribe.
     */
    public function __construct(
        public readonly string $email,
        public readonly ?string $firstName,
        public readonly array $fields,
        public readonly array $tagIds,
        public readonly ?int $kitFormId,
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
        $tagIds = array_slice( $this->tagIds, 0, self::MAX_TAGS_PER_JOB );

        $attributes = [
            'first_name'  => $this->firstName,
            'fields'      => $this->fields,
            'tag_ids'     => array_values( array_map( 'intval', $tagIds ) ),
            'kit_form_id' => $this->kitFormId,
        ];

        /**
         * Fired immediately before the Kit subscribe API call.
         *
         * The shared audit / analytics / debug seam for every subscribe
         * dispatched through this job — direct-form and raw subscribers
         * both flow through here.
         *
         * @hook  ap.convertkit.subscribing
         *
         * @since 1.1.0
         *
         * @param string               $email       Email address about to be subscribed.
         * @param array<string, mixed> $attributes  Payload map: `first_name`, `fields`, `tag_ids`, `kit_form_id`.
         */
        doAction( 'ap.convertkit.subscribing', $this->email, $attributes );

        try {
            if ( null !== $this->kitFormId ) {
                $subscriber = $convertKit->forms()->subscribe(
                    $this->kitFormId,
                    $this->email,
                    $this->fields,
                    $tagIds,
                );
            } else {
                $subscriber = $convertKit->subscribers()->create( $this->email, $this->firstName, $this->fields );

                foreach ( $tagIds as $tagId ) {
                    $convertKit->subscribers()->tag( $subscriber->id, (int) $tagId );
                }
            }
        } catch ( KitRateLimitException | KitServerException $e ) {
            /**
             * Fired when a subscribe attempt to Kit fails.
             *
             * Fires per handle() invocation, so a retryable transient
             * failure (rate limit, 5xx) that the queue will retry emits
             * one hook per attempt. Downstream can inspect the exception
             * type to distinguish transient from terminal.
             *
             * @hook  ap.convertkit.subscribeFailed
             *
             * @since 1.1.0
             *
             * @param string    $email      Email address that failed to subscribe.
             * @param Throwable $exception  The exception thrown by the Kit call.
             */
            doAction( 'ap.convertkit.subscribeFailed', $this->email, $e );

            throw $e;
        } catch ( Throwable $e ) {
            doAction( 'ap.convertkit.subscribeFailed', $this->email, $e );

            $this->fail( $e );

            return;
        }

        /**
         * Fired after a successful subscribe call to Kit.
         *
         * Standard audit / analytics / downstream-sync seam for the
         * public subscribe path.
         *
         * @hook  ap.convertkit.subscribed
         *
         * @since 1.1.0
         *
         * @param string               $email     Email address that was subscribed.
         * @param array<string, mixed> $response  Subscriber payload: `id`, `email`, `state`, `first_name`, `created_at`, `fields`.
         */
        doAction( 'ap.convertkit.subscribed', $this->email, [
            'id'         => $subscriber->id,
            'email'      => $subscriber->email,
            'state'      => $subscriber->state,
            'first_name' => $subscriber->firstName,
            'created_at' => $subscriber->createdAt,
            'fields'     => $subscriber->fields,
        ] );
    }
}
