<?php

/**
 * Validation for the public subscribe REST endpoint.
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
 * Accepts either a `feed_id` (uses that feed's `kit_form_id` and merges
 * its `kit_tag_ids` with the request's `tags`) OR a bare `kit_form_id`
 * for consumers who don't want the feed indirection.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class SubscribeRequest extends FormRequest
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
            'feed_id'     => [ 'required_without:kit_form_id', 'prohibits:kit_form_id', 'integer', 'min:1' ],
            'kit_form_id' => [ 'required_without:feed_id', 'integer', 'min:1' ],
            'email'       => [ 'required', 'email', 'max:255' ],
            'first_name'  => [ 'nullable', 'string', 'max:255' ],
            'fields'      => [ 'sometimes', 'array', 'max:32' ],
            'fields.*'    => [ 'nullable', 'string', 'max:2048' ],
            'tags'        => [ 'sometimes', 'array', 'max:20' ],
            'tags.*'      => [ 'integer', 'min:1' ],
        ];
    }
}
