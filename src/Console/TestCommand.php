<?php

/**
 * `convertkit:test` Artisan command.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Console;

use ArtisanPackUI\ConvertKit\Api\Client;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitAuthException;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitException;
use ArtisanPackUI\ConvertKit\Api\Exceptions\KitServerException;
use Illuminate\Console\Command;

/**
 * Verifies that the configured Kit API key can talk to the Kit v4 API.
 *
 * Pings `/account` and reports success (with account name + plan) or one of
 * three failure modes: no API key configured, auth failed (bad key), or
 * network error. Exits non-zero on any failure so it's usable in CI.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class TestCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'convertkit:test';

    /**
     * @var string
     */
    protected $description = 'Verify that the configured Kit API key can reach the Kit v4 API.';

    public function handle( Client $client ): int
    {
        if ( null === config( 'convertkit.api_key' ) || '' === config( 'convertkit.api_key' ) ) {
            $this->error( 'Kit API key is not configured.' );
            $this->line( 'Set CONVERTKIT_API_KEY in your environment or publish config/convertkit.php.' );

            return self::INVALID;
        }

        try {
            $response = $client->get( 'account' );
        } catch ( KitAuthException $e ) {
            $this->error( 'Authentication failed. The Kit API rejected the configured API key.' );
            $this->line( $e->getMessage() );

            return self::FAILURE;
        } catch ( KitServerException $e ) {
            $this->error( 'Could not reach the Kit API.' );
            $this->line( $e->getMessage() );

            return self::FAILURE;
        } catch ( KitException $e ) {
            $this->error( 'Kit API returned an unexpected error.' );
            $this->line( $e->getMessage() );

            return self::FAILURE;
        }

        $account = is_array( $response['account'] ?? null ) ? $response['account'] : $response;
        $name    = (string) ( $account['name'] ?? $account['account_name'] ?? 'unknown' );
        $plan    = (string) ( $account['plan_type'] ?? $account['plan'] ?? 'unknown' );

        $this->info( 'Kit API is reachable.' );
        $this->line( "  Account: {$name}" );
        $this->line( "  Plan:    {$plan}" );

        return self::SUCCESS;
    }
}
