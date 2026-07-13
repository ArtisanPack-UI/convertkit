<?php

/**
 * Create the convertkit_feeds table.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create( 'convertkit_feeds', function ( Blueprint $table ): void {
            $table->id();

            $table->unsignedBigInteger( 'form_id' );

            $table->string( 'name' );

            $table->unsignedBigInteger( 'kit_form_id' )->nullable();

            $table->json( 'kit_tag_ids' );

            $table->json( 'field_map' );

            $table->json( 'conditional_logic' )->nullable();

            $table->boolean( 'is_active' )->default( true );

            $table->timestamps();

            $table->unique( [ 'form_id', 'name' ] );
            $table->index( 'form_id' );
            $table->index( 'is_active' );
        } );
    }

    public function down(): void
    {
        Schema::dropIfExists( 'convertkit_feeds' );
    }
};
