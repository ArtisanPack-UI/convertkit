<?php

/**
 * ConvertKit helper functions.
 *
 * This file contains global helper functions for the ConvertKit package.
 * Add custom helper functions below.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

use ArtisanPackUI\ConvertKit\ConvertKit;

if ( ! function_exists( 'convertkit' ) ) {
    /**
     * Get the ConvertKit instance.
     *
     * @since 1.0.0
     *
     * @return ConvertKit
     */
    function convertkit(): ConvertKit
    {
        return app( 'convertkit' );
    }
}

// Add custom helper functions below
