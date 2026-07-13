<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Models\KitFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses( RefreshDatabase::class );

it( 'lists all feeds', function (): void {
    KitFeed::factory()->create( [ 'name' => 'Newsletter', 'form_id' => 1 ] );
    KitFeed::factory()->create( [ 'name' => 'Marketing', 'form_id' => 2 ] );

    $this->artisan( 'convertkit:feeds', [ 'action' => 'list' ] )
        ->expectsOutputToContain( 'Newsletter' )
        ->expectsOutputToContain( 'Marketing' )
        ->assertExitCode( 0 );
} );

it( 'filters feeds by form id', function (): void {
    KitFeed::factory()->create( [ 'name' => 'Alpha', 'form_id' => 1 ] );
    KitFeed::factory()->create( [ 'name' => 'Beta', 'form_id' => 2 ] );

    $this->artisan( 'convertkit:feeds list --form=1' )
        ->expectsOutputToContain( 'Alpha' )
        ->doesntExpectOutputToContain( 'Beta' )
        ->assertExitCode( 0 );
} );

it( 'reports no feeds when the database is empty', function (): void {
    $this->artisan( 'convertkit:feeds', [ 'action' => 'list' ] )
        ->expectsOutput( 'No feeds found.' )
        ->assertExitCode( 0 );
} );

it( 'creates a feed via the interactive wizard', function (): void {
    $this->artisan( 'convertkit:feeds', [ 'action' => 'create' ] )
        ->expectsQuestion( 'Which form id should this feed listen to?', '5' )
        ->expectsQuestion( 'Give the feed a name', 'Newsletter' )
        ->expectsQuestion( 'Kit form id (optional — leave blank for a raw subscribe)', '12345' )
        ->expectsQuestion( 'Which submission field slug holds the email address?', 'email' )
        ->expectsQuestion( 'Comma-separated Kit tag ids to apply (optional)', '10,20' )
        ->assertExitCode( 0 );

    $feed = KitFeed::where( 'name', 'Newsletter' )->first();

    expect( $feed )->not->toBeNull();
    expect( $feed->form_id )->toBe( 5 );
    expect( $feed->kit_form_id )->toBe( 12345 );
    expect( $feed->kit_tag_ids )->toBe( [ 10, 20 ] );
    expect( $feed->field_map )->toBe( [ 'email_address' => 'email' ] );
} );

it( 'deletes a feed when confirmed', function (): void {
    $feed = KitFeed::factory()->create( [ 'name' => 'Doomed' ] );

    $this->artisan( 'convertkit:feeds', [ 'action' => 'delete', 'id' => $feed->id ] )
        ->expectsConfirmation( "Delete feed #{$feed->id} (Doomed)?", 'yes' )
        ->assertExitCode( 0 );

    expect( KitFeed::find( $feed->id ) )->toBeNull();
} );

it( 'aborts delete when declined', function (): void {
    $feed = KitFeed::factory()->create();

    $this->artisan( 'convertkit:feeds', [ 'action' => 'delete', 'id' => $feed->id ] )
        ->expectsConfirmation( "Delete feed #{$feed->id} ({$feed->name})?", 'no' )
        ->expectsOutput( 'Aborted.' )
        ->assertExitCode( 0 );

    expect( KitFeed::find( $feed->id ) )->not->toBeNull();
} );

it( 'errors when delete is given a missing id', function (): void {
    $this->artisan( 'convertkit:feeds', [ 'action' => 'delete', 'id' => 9999 ] )
        ->expectsOutputToContain( 'No feed with id 9999' )
        ->assertExitCode( 1 );
} );

it( 'errors on an unknown action', function (): void {
    $this->artisan( 'convertkit:feeds', [ 'action' => 'nope' ] )
        ->expectsOutputToContain( "Unknown action 'nope'" )
        ->assertExitCode( 2 );
} );
