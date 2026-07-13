<?php

/**
 * JSON representation of a KitFeed.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Http\Resources;

use ArtisanPackUI\ConvertKit\Models\KitFeed;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin KitFeed
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class KitFeedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray( Request $request ): array
    {
        return [
            'id'                => $this->id,
            'form_id'           => $this->form_id,
            'name'              => $this->name,
            'kit_form_id'       => $this->kit_form_id,
            'kit_tag_ids'       => $this->kit_tag_ids,
            'field_map'         => $this->field_map,
            'conditional_logic' => $this->conditional_logic,
            'is_active'         => $this->is_active,
            'created_at'        => optional( $this->created_at )->toIso8601String(),
            'updated_at'        => optional( $this->updated_at )->toIso8601String(),
        ];
    }
}
