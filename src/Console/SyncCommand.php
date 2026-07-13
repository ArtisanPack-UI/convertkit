<?php

/**
 * `convertkit:sync` Artisan command.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Console;

use ArtisanPackUI\ConvertKit\Api\Exceptions\KitException;
use ArtisanPackUI\ConvertKit\ConvertKit;
use Illuminate\Console\Command;

/**
 * Force-refresh the cached reference data (forms, tags, custom fields).
 *
 * Usage:
 *   php artisan convertkit:sync            # all
 *   php artisan convertkit:sync forms
 *   php artisan convertkit:sync tags
 *   php artisan convertkit:sync fields
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class SyncCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'convertkit:sync {resource? : Which cache to refresh (forms, tags, fields). Omit to refresh all.}';

    /**
     * @var string
     */
    protected $description = 'Refresh the cached Kit reference data (forms, tags, custom fields).';

    public function handle( ConvertKit $convertkit ): int
    {
        $resource = $this->argument( 'resource' );

        $targets = null === $resource
            ? [ 'forms', 'tags', 'fields' ]
            : [ (string) $resource ];

        foreach ( $targets as $target ) {
            if ( ! in_array( $target, [ 'forms', 'tags', 'fields' ], true ) ) {
                $this->error( "Unknown resource '{$target}'. Choose one of: forms, tags, fields." );

                return self::INVALID;
            }
        }

        foreach ( $targets as $target ) {
            try {
                $count = match ( $target ) {
                    'forms'  => count( $convertkit->forms()->refresh() ),
                    'tags'   => count( $convertkit->tags()->refresh() ),
                    'fields' => count( $convertkit->customFields()->refresh() ),
                };
            } catch ( KitException $e ) {
                $this->error( "Failed to refresh {$target}: " . $e->getMessage() );

                return self::FAILURE;
            }

            $this->info( "Refreshed {$target}: {$count} record(s)." );
        }

        return self::SUCCESS;
    }
}
