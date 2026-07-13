<?php

/**
 * `convertkit:feeds` Artisan command.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Console;

use ArtisanPackUI\ConvertKit\Models\KitFeed;
use Illuminate\Console\Command;

/**
 * CLI feed management for teams that don't want to build a full admin
 * UI yet. Supports three actions: `list`, `create` (interactive wizard),
 * and `delete` (with confirmation).
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class FeedsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'convertkit:feeds
        {action : One of list, create, delete}
        {id? : Feed id (required for delete)}
        {--form= : Filter list results by form id}';

    /**
     * @var string
     */
    protected $description = 'Manage ConvertKit feeds from the command line (list, create, delete).';

    public function handle(): int
    {
        $action = (string) $this->argument( 'action' );

        return match ( $action ) {
            'list'   => $this->listFeeds(),
            'create' => $this->createFeed(),
            'delete' => $this->deleteFeed(),
            default  => $this->invalidAction( $action ),
        };
    }

    protected function listFeeds(): int
    {
        $query = KitFeed::query()->orderBy( 'id' );

        $formId = $this->option( 'form' );

        if ( null !== $formId && '' !== $formId ) {
            $query->where( 'form_id', (int) $formId );
        }

        $feeds = $query->get();

        if ( $feeds->isEmpty() ) {
            $this->info( 'No feeds found.' );

            return self::SUCCESS;
        }

        $this->table(
            [ 'ID', 'Form', 'Name', 'Kit Form', 'Tags', 'Active' ],
            $feeds->map( fn ( KitFeed $feed ): array => [
                $feed->id,
                $feed->form_id,
                $feed->name,
                $feed->kit_form_id ?? '—',
                is_array( $feed->kit_tag_ids ) ? implode( ',', $feed->kit_tag_ids ) : '',
                $feed->is_active ? 'yes' : 'no',
            ] )->all(),
        );

        return self::SUCCESS;
    }

    protected function createFeed(): int
    {
        $formId       = (int) $this->ask( 'Which form id should this feed listen to?' );
        $name         = (string) $this->ask( 'Give the feed a name' );
        $kitFormIdRaw = trim( (string) $this->ask( 'Kit form id (optional — leave blank for a raw subscribe)', '' ) );
        $emailSlug    = (string) $this->ask( 'Which submission field slug holds the email address?', 'email' );
        $tagsRaw      = trim( (string) $this->ask( 'Comma-separated Kit tag ids to apply (optional)', '' ) );

        $tagIds = '' === $tagsRaw
            ? []
            : array_values( array_filter( array_map(
                static fn ( string $t ): int => (int) trim( $t ),
                explode( ',', $tagsRaw ),
            ) ) );

        $feed = KitFeed::create( [
            'form_id'     => $formId,
            'name'        => $name,
            'kit_form_id' => '' === $kitFormIdRaw ? null : (int) $kitFormIdRaw,
            'kit_tag_ids' => $tagIds,
            'field_map'   => [ 'email_address' => $emailSlug ],
            'is_active'   => true,
        ] );

        $this->info( "Created feed #{$feed->id}: {$feed->name}" );
        $this->line( 'Edit the feed via the REST API to add more field mappings or conditional logic.' );

        return self::SUCCESS;
    }

    protected function deleteFeed(): int
    {
        $id = $this->argument( 'id' );

        if ( null === $id || '' === $id ) {
            $this->error( 'delete requires a feed id: php artisan convertkit:feeds delete {id}' );

            return self::INVALID;
        }

        $feed = KitFeed::query()->find( (int) $id );

        if ( null === $feed ) {
            $this->error( "No feed with id {$id} found." );

            return self::FAILURE;
        }

        if ( ! $this->confirm( "Delete feed #{$feed->id} ({$feed->name})?", false ) ) {
            $this->line( 'Aborted.' );

            return self::SUCCESS;
        }

        $feed->delete();
        $this->info( "Deleted feed #{$id}." );

        return self::SUCCESS;
    }

    protected function invalidAction( string $action ): int
    {
        $this->error( "Unknown action '{$action}'. Choose one of: list, create, delete." );

        return self::INVALID;
    }
}
