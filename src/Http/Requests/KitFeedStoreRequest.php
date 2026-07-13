<?php

/**
 * Validation for creating a KitFeed via the REST API.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Http\Requests;

use ArtisanPackUI\ConvertKit\Support\ConditionalLogicEvaluator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class KitFeedStoreRequest extends FormRequest
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
            'form_id'                                 => [ 'required', 'integer', 'min:1' ],
            'name'                                    => [ 'required', 'string', 'max:255' ],
            'kit_form_id'                             => [ 'nullable', 'integer', 'min:1' ],
            'kit_tag_ids'                             => [ 'array' ],
            'kit_tag_ids.*'                           => [ 'integer', 'min:1' ],
            'field_map'                               => [ 'required', 'array', 'min:1' ],
            'field_map.email_address'                 => [ 'required', 'string', 'max:255' ],
            'field_map.*'                             => [ 'string', 'max:255' ],
            'conditional_logic'                       => [ 'nullable', 'array' ],
            'conditional_logic.match'                 => [ 'nullable', 'string', Rule::in( [ 'all', 'any' ] ) ],
            'conditional_logic.conditions'            => [ 'nullable', 'array' ],
            'conditional_logic.conditions.*.field'    => [ 'required_with:conditional_logic.conditions', 'string' ],
            'conditional_logic.conditions.*.operator' => [
                'required_with:conditional_logic.conditions',
                'string',
                Rule::in( ConditionalLogicEvaluator::OPERATORS ),
            ],
            'conditional_logic.conditions.*.value'    => [ 'nullable' ],
            'is_active'                               => [ 'boolean' ],
        ];
    }
}
