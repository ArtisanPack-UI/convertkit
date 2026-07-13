<?php

/**
 * Evaluates a feed's conditional logic against a form submission.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\ConvertKit\Support;

/**
 * Pure evaluator that decides whether a feed should fire against a given
 * submission. Handles six operators (`equals`, `not_equals`, `contains`,
 * `not_contains`, `is_empty`, `is_not_empty`) under either `match: all`
 * (AND) or `match: any` (OR) semantics. Null/empty rules always fire.
 *
 * @package    ArtisanPack_UI
 * @subpackage ConvertKit
 *
 * @since      1.0.0
 */
class ConditionalLogicEvaluator
{
    public const OPERATORS = [
        'equals',
        'not_equals',
        'contains',
        'not_contains',
        'is_empty',
        'is_not_empty',
    ];

    /**
     * Decide whether the feed should fire.
     *
     * @param  array<string, mixed>|null  $rules  Rule set with `match` and
     *                                            `conditions`, or null to
     *                                            always fire.
     * @param  array<string, mixed>  $submissionValues  Submission values keyed
     *                                                  by field slug.
     */
    public function evaluate( ?array $rules, array $submissionValues ): bool
    {
        if ( null === $rules || [] === $rules ) {
            return true;
        }

        $conditions = $rules['conditions'] ?? [];

        if ( ! is_array( $conditions ) || [] === $conditions ) {
            return true;
        }

        $match = strtolower( (string) ( $rules['match'] ?? 'all' ) );

        if ( 'any' === $match ) {
            foreach ( $conditions as $condition ) {
                if ( is_array( $condition ) && $this->evaluateCondition( $condition, $submissionValues ) ) {
                    return true;
                }
            }

            return false;
        }

        foreach ( $conditions as $condition ) {
            if ( ! is_array( $condition ) ) {
                return false;
            }

            if ( ! $this->evaluateCondition( $condition, $submissionValues ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $condition
     * @param  array<string, mixed>  $submissionValues
     */
    protected function evaluateCondition( array $condition, array $submissionValues ): bool
    {
        $field    = (string) ( $condition['field'] ?? '' );
        $operator = (string) ( $condition['operator'] ?? '' );
        $expected = $condition['value'] ?? null;

        if ( '' === $field || '' === $operator ) {
            return false;
        }

        $actual = $submissionValues[ $field ] ?? null;

        return match ( $operator ) {
            'equals'       => $this->normalize( $actual ) === $this->normalize( $expected ),
            'not_equals'   => $this->normalize( $actual ) !== $this->normalize( $expected ),
            'contains'     => $this->contains( $actual, $expected ),
            'not_contains' => ! $this->contains( $actual, $expected ),
            'is_empty'     => $this->isEmpty( $actual ),
            'is_not_empty' => ! $this->isEmpty( $actual ),
            default        => false,
        };
    }

    protected function normalize( mixed $value ): string
    {
        if ( null === $value ) {
            return '';
        }

        if ( is_bool( $value ) ) {
            return $value ? '1' : '0';
        }

        if ( is_scalar( $value ) ) {
            return (string) $value;
        }

        return json_encode( $value ) ?: '';
    }

    protected function contains( mixed $actual, mixed $expected ): bool
    {
        $needle = $this->normalize( $expected );

        if ( '' === $needle ) {
            return false;
        }

        if ( is_array( $actual ) ) {
            foreach ( $actual as $item ) {
                if ( $this->normalize( $item ) === $needle ) {
                    return true;
                }

                if ( str_contains( $this->normalize( $item ), $needle ) ) {
                    return true;
                }
            }

            return false;
        }

        return str_contains( $this->normalize( $actual ), $needle );
    }

    protected function isEmpty( mixed $value ): bool
    {
        if ( null === $value ) {
            return true;
        }

        if ( is_string( $value ) ) {
            return '' === trim( $value );
        }

        if ( is_array( $value ) ) {
            return [] === $value;
        }

        return false;
    }
}
