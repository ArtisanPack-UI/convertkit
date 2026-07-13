<?php

/**
 * KitFeed model factory.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Database\Factories;

use ArtisanPackUI\ConvertKit\Models\KitFeed;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KitFeed>
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class KitFeedFactory extends Factory
{
    protected $model = KitFeed::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id'           => $this->faker->numberBetween( 1, 1000 ),
            'name'              => 'Feed ' . $this->faker->unique()->words( 2, true ),
            'kit_form_id'       => $this->faker->numberBetween( 100000, 999999 ),
            'kit_tag_ids'       => [],
            'field_map'         => [
                'email_address' => 'email',
            ],
            'conditional_logic' => null,
            'is_active'         => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state( fn (): array => [ 'is_active' => false ] );
    }
}
