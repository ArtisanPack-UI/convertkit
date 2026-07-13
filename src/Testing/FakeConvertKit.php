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

use ArtisanPackUI\ConvertKit\Api\DTOs\Subscriber;
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
     * Map from Kit subscriber id → email so tag()/untag() calls that only
     * carry the id can be resolved back to a human-readable address in
     * `assertTagged()`.
     *
     * @var array<int, string>
     */
    protected array $subscriberEmails = [];

    protected int $nextSubscriberId = 1;

    protected FakeSubscribersEndpoint $subscribersFake;

    protected FakeFormsEndpoint $formsFake;

    protected FakeTagsEndpoint $tagsFake;

    protected FakeCustomFieldsEndpoint $customFieldsFake;

    public function __construct()
    {
        // Intentionally skip parent constructor — the fake owns its own
        // endpoint instances and never touches EndpointFactory.
        $this->subscribersFake  = new FakeSubscribersEndpoint( $this );
        $this->formsFake        = new FakeFormsEndpoint( $this );
        $this->tagsFake         = new FakeTagsEndpoint( $this );
        $this->customFieldsFake = new FakeCustomFieldsEndpoint( $this );
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
}
