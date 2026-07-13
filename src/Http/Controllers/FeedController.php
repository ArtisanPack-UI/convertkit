<?php

/**
 * REST CRUD controller for KitFeeds.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Http\Controllers;

use ArtisanPackUI\ConvertKit\Http\Requests\KitFeedStoreRequest;
use ArtisanPackUI\ConvertKit\Http\Requests\KitFeedUpdateRequest;
use ArtisanPackUI\ConvertKit\Http\Resources\KitFeedResource;
use ArtisanPackUI\ConvertKit\Models\KitFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * REST endpoints for feed management. Devs use these to build their own
 * admin UI (Livewire, React, Vue, etc.) — no UI ships in the package.
 *
 * Every action calls `Gate::authorize($ability, $convertkitFeed)` (or the query
 * builder for `index`) so a policy consumer can scope access per record —
 * e.g. reject when `$user->id !== $convertkitFeed->form->owner_id`. An empty
 * `feed_admin.gate_ability` config value is treated as misconfiguration
 * and fails closed with a 403; there is no "disable auth" escape hatch.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class FeedController extends Controller
{
    /**
     * Default gate ability used when the config value is missing or empty.
     * We fall back to this rather than skipping the check so a blank env
     * var never opens the API.
     */
    protected const DEFAULT_ABILITY = 'manage-convertkit-feeds';

    public function index( Request $request ): AnonymousResourceCollection
    {
        $this->authorizeAction();

        $query = KitFeed::query()->orderBy( 'id' );

        if ( $request->filled( 'form_id' ) ) {
            $query->where( 'form_id', (int) $request->query( 'form_id' ) );
        }

        return KitFeedResource::collection( $query->get() );
    }

    public function store( KitFeedStoreRequest $request ): KitFeedResource
    {
        $this->authorizeAction();

        $convertkitFeed = KitFeed::create( $request->validated() );

        return new KitFeedResource( $convertkitFeed );
    }

    public function show( KitFeed $convertkitFeed ): KitFeedResource
    {
        $this->authorizeAction( $convertkitFeed );

        return new KitFeedResource( $convertkitFeed );
    }

    public function update( KitFeedUpdateRequest $request, KitFeed $convertkitFeed ): KitFeedResource
    {
        $this->authorizeAction( $convertkitFeed );

        $convertkitFeed->fill( $request->validated() )->save();

        return new KitFeedResource( $convertkitFeed->fresh() );
    }

    public function destroy( KitFeed $convertkitFeed ): JsonResponse
    {
        $this->authorizeAction( $convertkitFeed );

        $convertkitFeed->delete();

        return response()->json( null, 204 );
    }

    /**
     * Enforce the admin gate for the current action.
     *
     * When called with a `$convertkitFeed`, the gate closure receives it as the
     * second argument — consumers can then key on `$convertkitFeed->form_id` (or
     * whatever ownership rule fits) to scope access per record.
     */
    protected function authorizeAction( ?KitFeed $convertkitFeed = null ): void
    {
        $ability = trim( (string) config( 'convertkit.feed_admin.gate_ability', self::DEFAULT_ABILITY ) );

        // Fail closed on empty / whitespace-only configuration. An empty
        // ability is treated as misconfiguration rather than an "auth off"
        // toggle so a blank CONVERTKIT_FEED_ADMIN_ABILITY env var never
        // opens the API.
        if ( '' === $ability ) {
            $ability = self::DEFAULT_ABILITY;
        }

        $arguments = null === $convertkitFeed ? [] : [ $convertkitFeed ];

        if ( ! Gate::allows( $ability, $arguments ) ) {
            abort( 403 );
        }
    }
}
