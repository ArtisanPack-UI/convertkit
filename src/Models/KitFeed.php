<?php

/**
 * KitFeed Eloquent model.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Models;

use ArtisanPackUI\ConvertKit\Database\Factories\KitFeedFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Persistence layer for a single Kit feed — the join between a form (from
 * `artisanpack-ui/forms`) and Kit.
 *
 * The parent form model is resolved via
 * `config('convertkit.forms_integration.form_model')` so the forms package
 * stays an optional peer.
 *
 * @property int $id
 * @property int $form_id
 * @property string $name
 * @property int|null $kit_form_id
 * @property array<int, int|string> $kit_tag_ids
 * @property array<string, string> $field_map
 * @property array<string, mixed>|null $conditional_logic
 * @property bool $is_active
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class KitFeed extends Model
{
    use HasFactory;

    protected $table = 'convertkit_feeds';

    protected $guarded = [ 'id' ];

    /**
     * Column defaults applied to freshly created rows.
     *
     * `kit_tag_ids` is declared NOT NULL at the schema level, so a
     * create() call that omits it would trip a constraint violation.
     * Fill an empty JSON array by default so the store request can
     * treat the field as optional.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'kit_tag_ids' => '[]',
    ];

    /**
     * The form this feed belongs to.
     *
     * The related model class is resolved from config at call time so the
     * forms package can remain an optional peer. When the configured
     * class is missing or the config value is empty, we throw a
     * descriptive `RuntimeException` at relation access time rather than
     * letting Eloquent surface an opaque `Class "" not found` fatal.
     */
    public function form(): BelongsTo
    {
        $model = ltrim( (string) config( 'convertkit.forms_integration.form_model' ), '\\' );

        if ( '' === $model || ! class_exists( $model ) ) {
            throw new RuntimeException( sprintf(
                'KitFeed::form() requires convertkit.forms_integration.form_model to point at an existing class; got "%s". Install artisanpack-ui/forms or set CONVERTKIT_FORMS_MODEL to your own form model FQCN.',
                $model,
            ) );
        }

        return $this->belongsTo( $model, 'form_id' );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kit_tag_ids'       => 'array',
            'field_map'         => 'array',
            'conditional_logic' => 'array',
            'is_active'         => 'boolean',
        ];
    }

    /**
     * @return Factory<static>
     */
    protected static function newFactory(): Factory
    {
        return KitFeedFactory::new();
    }
}
