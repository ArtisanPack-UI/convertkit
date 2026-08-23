<?php

/**
 * Test double for the ConvertKit facade / service.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Testing;

use ArtisanPackUI\ConvertKit\Api\DTOs\Broadcast;
use ArtisanPackUI\ConvertKit\Api\DTOs\GrowthStats;
use ArtisanPackUI\ConvertKit\Api\DTOs\Subscriber;
use ArtisanPackUI\ConvertKit\Api\Endpoints\AccountEndpoint;
use ArtisanPackUI\ConvertKit\Api\Endpoints\BroadcastsEndpoint;
use ArtisanPackUI\ConvertKit\Api\Endpoints\CustomFieldsEndpoint;
use ArtisanPackUI\ConvertKit\Api\Endpoints\FormsEndpoint;
use ArtisanPackUI\ConvertKit\Api\Endpoints\SubscribersEndpoint;
use ArtisanPackUI\ConvertKit\Api\Endpoints\TagsEndpoint;
use ArtisanPackUI\ConvertKit\ConvertKit;
use PHPUnit\Framework\Assert;

/**
 * Recording ConvertKit instance that consumer apps can install with
 * `ArtisanPackUI\ConvertKit\Facades\ConvertKit::fake()`. Captures every
 * subscribe / tag / untag call and exposes PHPUnit-style assertions.
 *
 * The fake never talks to Kit — every method returns synthesized DTOs
 * with predictable ids so callers can compose assertions without a
 * network round trip.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class FakeConvertKit extends ConvertKit
{
    /**
     * @var array<int, array{email: string, first_name: ?string, fields: array<string, mixed>, form_id: ?int, tags: array<int, int>}>
     */
    public array $subscribed = [];

    /**
     * @var array<int, array{email: string, tag_id: int}>
     */
    public array $tagged = [];

    /**
     * @var array<int, array{email: string, tag_id: int}>
     */
    public array $untagged = [];

    /**
     * Read-call log for `account()->stats()` / `account()->refresh()`.
     *
     * @var array<int, array{starting: ?string, ending: ?string}>
     */
    public array $statsRequests = [];

    /**
     * Read-call log for `account()->growthSeries()`.
     *
     * @var array<int, array{starting: string, ending: string, interval: string}>
     */
    public array $growthSeriesRequests = [];

    /**
     * Read-call log for `broadcasts()->list()` / `broadcasts()->refresh()`.
     *
     * @var array<int, array{limit: int}>
     */
    public array $broadcastsRequests = [];

    /**
     * Map from Kit subscriber id → email so tag()/untag() calls that only
     * carry the id can be resolved back to a human-readable address in
     * `assertTagged()`.
     *
     * @var array<int, string>
     */
    protected array $subscriberEmails = [];

    /**
     * Seeded aggregate returned by `account()->stats()` / `refresh()`, or null
     * to fall back to a zero-valued aggregate.
     */
    protected ?GrowthStats $statsFixture = null;

    /**
     * Seeded growth series returned verbatim by `account()->growthSeries()`, or
     * null to fall back to one zero-valued point per interval bucket.
     *
     * @var array<int, GrowthStats>|null
     */
    protected ?array $growthSeriesFixture = null;

    /**
     * Seeded broadcasts returned newest-first ( sliced to the requested limit )
     * by `broadcasts()->list()` / `refresh()`, or null to fall back to an empty
     * list.
     *
     * @var array<int, Broadcast>|null
     */
    protected ?array $broadcastsFixture = null;

    protected int $nextSubscriberId = 1;

    protected FakeSubscribersEndpoint $subscribersFake;

    protected FakeFormsEndpoint $formsFake;

    protected FakeTagsEndpoint $tagsFake;

    protected FakeCustomFieldsEndpoint $customFieldsFake;

    protected FakeAccountEndpoint $accountFake;

    protected FakeBroadcastsEndpoint $broadcastsFake;

    public function __construct()
    {
        // Intentionally skip parent constructor — the fake owns its own
        // endpoint instances and never touches EndpointFactory.
        $this->subscribersFake  = new FakeSubscribersEndpoint( $this );
        $this->formsFake        = new FakeFormsEndpoint( $this );
        $this->tagsFake         = new FakeTagsEndpoint( $this );
        $this->customFieldsFake = new FakeCustomFieldsEndpoint( $this );
        $this->accountFake      = new FakeAccountEndpoint( $this );
        $this->broadcastsFake   = new FakeBroadcastsEndpoint( $this );
    }

    public function subscribers(): SubscribersEndpoint
    {
        return $this->subscribersFake;
    }

    public function forms(): FormsEndpoint
    {
        return $this->formsFake;
    }

    public function tags(): TagsEndpoint
    {
        return $this->tagsFake;
    }

    public function customFields(): CustomFieldsEndpoint
    {
        return $this->customFieldsFake;
    }

    public function account(): AccountEndpoint
    {
        return $this->accountFake;
    }

    public function broadcasts(): BroadcastsEndpoint
    {
        return $this->broadcastsFake;
    }

    /**
     * Seed the aggregate that `account()->stats()` and `account()->refresh()`
     * return. Pass a ready-made GrowthStats, or the individual counts for a
     * quick fixture. The requested window is echoed onto the result at read
     * time, so any `starting`/`ending` on a supplied GrowthStats is ignored.
     *
     * @since 1.2.0
     */
    public function fakeStats(
        GrowthStats|int $subscribers,
        int $netNewSubscribers = 0,
        int $newSubscribers = 0,
        int $cancellations = 0,
    ): self {
        $this->statsFixture = $subscribers instanceof GrowthStats
            ? $subscribers
            : new GrowthStats( $subscribers, $netNewSubscribers, $newSubscribers, $cancellations );

        return $this;
    }

    /**
     * Seed the growth series that `account()->growthSeries()` returns. The
     * points are returned verbatim, so the caller controls the shape of the
     * series; range/interval validation still runs against the requested
     * window.
     *
     * @since 1.2.0
     */
    public function fakeGrowthSeries( GrowthStats ...$points ): self
    {
        $this->growthSeriesFixture = array_values( $points );

        return $this;
    }

    /**
     * Seed the broadcasts that `broadcasts()->list()` and `refresh()` return.
     * Pass them newest-first; each read slices to its requested limit, mirroring
     * "the $limit most recent broadcasts".
     *
     * @since 1.2.0
     */
    public function fakeBroadcasts( Broadcast ...$broadcasts ): self
    {
        $this->broadcastsFixture = array_values( $broadcasts );

        return $this;
    }

    /**
     * Record a subscribe call and return a synthesized Subscriber DTO.
     *
     * Kept public so the fake endpoint classes can push through it.
     *
     * @param  array<string, mixed>  $fields
     * @param  array<int, int|string>  $tags
     */
    public function recordSubscribe(
        string $email,
        ?string $firstName,
        array $fields,
        ?int $formId,
        array $tags,
    ): Subscriber {
        $id = $this->nextSubscriberId++;

        $this->subscriberEmails[ $id ] = $email;

        $this->subscribed[] = [
            'email'      => $email,
            'first_name' => $firstName,
            'fields'     => $fields,
            'form_id'    => $formId,
            'tags'       => array_values( array_map( 'intval', $tags ) ),
        ];

        return new Subscriber(
            id        : $id,
            email     : $email,
            state     : 'active',
            firstName : $firstName,
            createdAt : null,
            fields    : $fields,
        );
    }

    public function recordTag( int $subscriberId, int $tagId ): void
    {
        $this->tagged[] = [
            'email'  => $this->subscriberEmails[ $subscriberId ] ?? '',
            'tag_id' => $tagId,
        ];
    }

    public function recordUntag( int $subscriberId, int $tagId ): void
    {
        $this->untagged[] = [
            'email'  => $this->subscriberEmails[ $subscriberId ] ?? '',
            'tag_id' => $tagId,
        ];
    }

    /**
     * Record a stats read and return the seeded aggregate ( or zeros ) with the
     * requested window echoed onto it.
     *
     * Kept public so the fake account endpoint can push through it.
     *
     * @since 1.2.0
     */
    public function resolveStats( ?string $starting, ?string $ending ): GrowthStats
    {
        $this->statsRequests[] = [
            'starting' => $starting,
            'ending'   => $ending,
        ];

        $fixture = $this->statsFixture;

        return new GrowthStats(
            null === $fixture ? 0 : $fixture->subscribers,
            null === $fixture ? 0 : $fixture->netNewSubscribers,
            null === $fixture ? 0 : $fixture->newSubscribers,
            null === $fixture ? 0 : $fixture->cancellations,
            $starting,
            $ending,
        );
    }

    /**
     * Record a growth-series read and return the seeded series verbatim, or one
     * zero-valued point per bucket when nothing was seeded.
     *
     * Kept public so the fake account endpoint can push through it. The endpoint
     * computes `$buckets` ( which also validates the range/interval ) before
     * handing them here.
     *
     * @since 1.2.0
     *
     * @param  array<int, array{0: string, 1: string}>  $buckets
     *
     * @return array<int, GrowthStats>
     */
    public function resolveGrowthSeries( string $starting, string $ending, string $interval, array $buckets ): array
    {
        $this->growthSeriesRequests[] = [
            'starting' => $starting,
            'ending'   => $ending,
            'interval' => $interval,
        ];

        if ( null !== $this->growthSeriesFixture ) {
            return $this->growthSeriesFixture;
        }

        return array_map(
            static fn ( array $bucket ): GrowthStats => new GrowthStats( 0, 0, 0, 0, $bucket[0], $bucket[1] ),
            $buckets,
        );
    }

    /**
     * Record a broadcasts read and return the seeded broadcasts ( sliced to the
     * limit ), or an empty list when nothing was seeded.
     *
     * Kept public so the fake broadcasts endpoint can push through it.
     *
     * @since 1.2.0
     *
     * @return array<int, Broadcast>
     */
    public function resolveBroadcasts( int $limit ): array
    {
        $this->broadcastsRequests[] = [ 'limit' => $limit ];

        if ( null === $this->broadcastsFixture ) {
            return [];
        }

        return array_slice( $this->broadcastsFixture, 0, $limit );
    }

    /**
     * Assert that an email was subscribed. When `$formId` is non-null
     * the match also requires the same Kit form id — pass null to
     * accept any form.
     */
    public function assertSubscribed( string $email, ?int $formId = null ): void
    {
        foreach ( $this->subscribed as $record ) {
            if ( $record['email'] !== $email ) {
                continue;
            }

            if ( null !== $formId && $record['form_id'] !== $formId ) {
                continue;
            }

            Assert::assertTrue( true );

            return;
        }

        Assert::fail( sprintf(
            'Expected %s to be subscribed%s but no matching subscribe call was recorded.',
            $email,
            null === $formId ? '' : " via Kit form id {$formId}",
        ) );
    }

    /**
     * Assert that a tag was applied to a given email. Matches either a
     * standalone `subscribers()->tag()` call or a `forms()->subscribe()`
     * that embedded the tag in its payload.
     */
    public function assertTagged( string $email, int $tagId ): void
    {
        foreach ( $this->tagged as $record ) {
            if ( $record['email'] === $email && $record['tag_id'] === $tagId ) {
                Assert::assertTrue( true );

                return;
            }
        }

        foreach ( $this->subscribed as $record ) {
            if ( $record['email'] === $email && in_array( $tagId, $record['tags'], true ) ) {
                Assert::assertTrue( true );

                return;
            }
        }

        Assert::fail( sprintf(
            'Expected %s to be tagged with tag id %d but no matching call was recorded.',
            $email,
            $tagId,
        ) );
    }

    /**
     * Assert no subscribe, tag, or untag calls were recorded.
     */
    public function assertNothingSent(): void
    {
        Assert::assertSame( [], $this->subscribed, 'Expected no subscribe calls, but some were recorded.' );
        Assert::assertSame( [], $this->tagged, 'Expected no tag calls, but some were recorded.' );
        Assert::assertSame( [], $this->untagged, 'Expected no untag calls, but some were recorded.' );
    }

    /**
     * Assert an exact number of subscribe calls were recorded.
     */
    public function assertSentCount( int $count ): void
    {
        Assert::assertCount(
            $count,
            $this->subscribed,
            sprintf( 'Expected %d subscribe call(s), got %d.', $count, count( $this->subscribed ) ),
        );
    }

    /**
     * Assert account growth stats were requested. Pass `$starting`/`$ending`
     * to require a matching window, or leave them null to accept any request.
     *
     * @since 1.2.0
     */
    public function assertStatsRequested( ?string $starting = null, ?string $ending = null ): void
    {
        foreach ( $this->statsRequests as $request ) {
            if ( null !== $starting && $request['starting'] !== $starting ) {
                continue;
            }

            if ( null !== $ending && $request['ending'] !== $ending ) {
                continue;
            }

            Assert::assertTrue( true );

            return;
        }

        Assert::fail( sprintf(
            'Expected account stats to be requested%s but no matching call was recorded.',
            null === $starting && null === $ending
                ? ''
                : " for the window {$starting} to {$ending}",
        ) );
    }

    /**
     * Assert a growth time series was requested. Pass any of
     * `$starting`/`$ending`/`$interval` to require a match, or leave them null
     * to accept any request.
     *
     * @since 1.2.0
     */
    public function assertGrowthSeriesRequested(
        ?string $starting = null,
        ?string $ending = null,
        ?string $interval = null,
    ): void {
        foreach ( $this->growthSeriesRequests as $request ) {
            if ( null !== $starting && $request['starting'] !== $starting ) {
                continue;
            }

            if ( null !== $ending && $request['ending'] !== $ending ) {
                continue;
            }

            if ( null !== $interval && $request['interval'] !== $interval ) {
                continue;
            }

            Assert::assertTrue( true );

            return;
        }

        Assert::fail( 'Expected a growth series to be requested but no matching call was recorded.' );
    }

    /**
     * Assert the recent broadcasts were listed. Pass `$limit` to require a
     * matching limit, or leave it null to accept any request.
     *
     * @since 1.2.0
     */
    public function assertBroadcastsListed( ?int $limit = null ): void
    {
        foreach ( $this->broadcastsRequests as $request ) {
            if ( null !== $limit && $request['limit'] !== $limit ) {
                continue;
            }

            Assert::assertTrue( true );

            return;
        }

        Assert::fail( sprintf(
            'Expected broadcasts to be listed%s but no matching call was recorded.',
            null === $limit ? '' : " with limit {$limit}",
        ) );
    }
}
