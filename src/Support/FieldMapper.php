<?php

/**
 * Maps a FormSubmission to a Kit-shaped payload using a feed's `field_map`.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Support;

/**
 * Pure translator from a form submission to the payload shape Kit expects.
 *
 * A feed's `field_map` is a flat associative array: keys are Kit destinations
 * (either the reserved `email_address` / `first_name` or a Kit custom-field
 * key), values are submission field slugs.
 *
 *   [
 *     'email_address' => 'email',
 *     'first_name'    => 'name',
 *     'company'       => 'company_name',
 *   ]
 *
 * Missing / unmapped submission slugs are ignored — a partial submission is
 * fine. The one exception: a missing `email_address` mapping (or a mapped
 * slug that resolves to an empty value) throws `FieldMapperException`, since
 * Kit cannot subscribe an anonymous address.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class FieldMapper
{
    /**
     * Reserved Kit destinations that surface as top-level payload keys.
     */
    protected const RESERVED_KEYS = [ 'email_address', 'first_name' ];

    /**
     * Map a submission to a Kit payload.
     *
     * The submission is duck-typed: any object exposing its values via a
     * public `values` property, a `getValues()` method, or Eloquent-style
     * attribute access is accepted. This keeps the mapper decoupled from
     * the concrete `FormSubmission` model shipped by `artisanpack-ui/forms`.
     *
     * @param  object  $submission  The form submission.
     * @param  array<string, string>  $fieldMap  Kit destination => submission slug.
     *
     * @throws FieldMapperException When no email address can be resolved.
     *
     * @return array<string, mixed>
     */
    public function map( object $submission, array $fieldMap ): array
    {
        $values = $this->extractValues( $submission );

        return $this->mapValues( $values, $fieldMap );
    }

    /**
     * Pure form of {@see map()} that operates on a values array directly.
     *
     * @param  array<string, mixed>  $submissionValues
     * @param  array<string, string>  $fieldMap
     *
     * @throws FieldMapperException
     *
     * @return array<string, mixed>
     */
    public function mapValues( array $submissionValues, array $fieldMap ): array
    {
        if ( ! array_key_exists( 'email_address', $fieldMap ) ) {
            throw new FieldMapperException(
                'Kit field map is missing the required `email_address` destination.',
            );
        }

        $payload = [];
        $fields  = [];

        foreach ( $fieldMap as $kitKey => $submissionSlug ) {
            if ( ! is_string( $kitKey ) || '' === $kitKey || ! is_string( $submissionSlug ) ) {
                continue;
            }

            if ( ! array_key_exists( $submissionSlug, $submissionValues ) ) {
                continue;
            }

            $value = $submissionValues[ $submissionSlug ];

            if ( in_array( $kitKey, self::RESERVED_KEYS, true ) ) {
                $payload[ $kitKey ] = $value;
                continue;
            }

            $fields[ $kitKey ] = $value;
        }

        if ( ! isset( $payload['email_address'] ) || '' === trim( (string) $payload['email_address'] ) ) {
            throw new FieldMapperException(
                'Submission is missing a value for the mapped `email_address` field.',
            );
        }

        if ( [] !== $fields ) {
            $payload['fields'] = $fields;
        }

        return $payload;
    }

    /**
     * Read the submission's values as a flat `field_slug => value` array.
     *
     * Kept public so callers that need to walk a submission once (the
     * event listener, other integrations) can share the extraction with
     * downstream mapping steps instead of duplicating the duck-type
     * ladder.
     *
     * @return array<string, mixed>
     */
    public function extractValues( object $submission ): array
    {
        if ( method_exists( $submission, 'getValues' ) ) {
            $values = $submission->getValues();

            return is_array( $values ) ? $values : [];
        }

        // The forms package's FormSubmission exposes an assoc array via the
        // `data_array` accessor and a Collection via `data`. Prefer those
        // over `values`, since `values` on that model is a HasMany
        // relationship that returns Eloquent rows, not field=>value pairs.
        $dataArray = $submission->data_array ?? null;

        if ( is_array( $dataArray ) ) {
            return $dataArray;
        }

        $data = $submission->data ?? null;

        if ( is_object( $data ) && method_exists( $data, 'toArray' ) ) {
            $flat = $data->toArray();

            if ( is_array( $flat ) ) {
                return $flat;
            }
        }

        if ( is_array( $submission->values ?? null ) ) {
            return $submission->values;
        }

        return [];
    }
}
