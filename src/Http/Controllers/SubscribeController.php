<?php

/**
 * Public REST endpoint for subscribing an email to Kit.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Http\Controllers;

use ArtisanPackUI\ConvertKit\Http\Requests\SubscribeRequest;
use ArtisanPackUI\ConvertKit\Jobs\SubscribeToKit;
use ArtisanPackUI\ConvertKit\Models\KitFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Endpoint devs point their front-end subscribe forms at (React, Vue,
 * Livewire, plain HTML). Actual Kit call happens on the queue — this
 * always returns `202 Accepted` once validation and feed lookup pass.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class SubscribeController extends Controller
{
    public function __invoke( SubscribeRequest $request ): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        $email     = (string) $data['email'];
        $firstName = isset( $data['first_name'] ) ? (string) $data['first_name'] : null;
        $fields    = is_array( $data['fields'] ?? null ) ? $data['fields'] : [];
        $tagIds    = is_array( $data['tags'] ?? null ) ? array_map( 'intval', $data['tags'] ) : [];
        $kitFormId = null;

        if ( isset( $data['feed_id'] ) ) {
            $feed = KitFeed::query()->where( 'is_active', true )->find( (int) $data['feed_id'] );

            if ( null === $feed ) {
                // Match Laravel's standard 422 validation shape so this
                // response is indistinguishable from other validation
                // failures — otherwise a distinct message would let an
                // attacker enumerate which feed IDs exist and are
                // active by probing 1..N and diffing responses.
                return response()->json( [
                    'message' => 'The given data was invalid.',
                    'errors'  => [ 'feed_id' => [ 'The feed id field is invalid.' ] ],
                ], 422 );
            }

            $kitFormId = $feed->kit_form_id;

            if ( is_array( $feed->kit_tag_ids ) ) {
                $tagIds = array_values( array_unique( array_map(
                    'intval',
                    array_merge( $feed->kit_tag_ids, $tagIds ),
                ) ) );
            }
        } else {
            $kitFormId = (int) $data['kit_form_id'];
        }

        SubscribeToKit::dispatch(
            $email,
            $firstName,
            $fields,
            $tagIds,
            $kitFormId,
        );

        return response()->json( [ 'message' => 'Subscribe queued.' ], 202 );
    }
}
