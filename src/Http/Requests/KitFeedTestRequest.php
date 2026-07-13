<?php

/**
 * Validation for the feed dry-run endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class KitFeedTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'values'   => [ 'required', 'array' ],
            'values.*' => [ 'nullable' ],
        ];
    }
}
