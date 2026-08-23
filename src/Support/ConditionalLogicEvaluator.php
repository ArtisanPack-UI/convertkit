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
 * submission. Handles eighteen operators aligned with the
 * `artisanpack-ui/forms` vocabulary (`equals`, `not_equals`, `contains`,
 * `not_contains`, `starts_with`, `ends_with`, `is_empty`, `is_not_empty`,
 * `greater_than`, `less_than`, `greater_or_equal`, `less_or_equal`, `in`,
 * `not_in`, `checked`, `unchecked`, `includes`, `not_includes`) under either
 * `match: all` (AND) or `match: any` (OR) semantics. Null/empty rules always
 * fire.
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
        'starts_with',
        'ends_with',
        'is_empty',
        'is_not_empty',
        'greater_than',
        'less_than',
        'greater_or_equal',
        'less_or_equal',
        'in',
        'not_in',
        'checked',
        'unchecked',
        'includes',
        'not_includes',
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
            'equals'           => $this->normalize( $actual ) === $this->normalize( $expected ),
            'not_equals'       => $this->normalize( $actual ) !== $this->normalize( $expected ),
            'contains'         => $this->contains( $actual, $expected ),
            'not_contains'     => ! $this->contains( $actual, $expected ),
            'starts_with'      => $this->startsWith( $actual, $expected ),
            'ends_with'        => $this->endsWith( $actual, $expected ),
            'is_empty'         => $this->isEmpty( $actual ),
            'is_not_empty'     => ! $this->isEmpty( $actual ),
            'greater_than'     => $this->compareNumeric( $actual, $expected, '>' ),
            'less_than'        => $this->compareNumeric( $actual, $expected, '<' ),
            'greater_or_equal' => $this->compareNumeric( $actual, $expected, '>=' ),
            'less_or_equal'    => $this->compareNumeric( $actual, $expected, '<=' ),
            'in'               => $this->inList( $actual, $expected ),
            'not_in'           => ! $this->inList( $actual, $expected ),
            'checked'          => $this->isChecked( $actual ),
            'unchecked'        => ! $this->isChecked( $actual ),
            'includes'         => $this->includes( $actual, $expected ),
            'not_includes'     => ! $this->includes( $actual, $expected ),
            default            => false,
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

    protected function startsWith( mixed $actual, mixed $expected ): bool
    {
        $needle = $this->normalize( $expected );

        if ( '' === $needle ) {
            return false;
        }

        return str_starts_with( $this->normalize( $actual ), $needle );
    }

    protected function endsWith( mixed $actual, mixed $expected ): bool
    {
        $needle = $this->normalize( $expected );

        if ( '' === $needle ) {
            return false;
        }

        return str_ends_with( $this->normalize( $actual ), $needle );
    }

    protected function compareNumeric( mixed $actual, mixed $expected, string $operator ): bool
    {
        if ( ! is_numeric( $actual ) || ! is_numeric( $expected ) ) {
            return false;
        }

        $left  = (float) $actual;
        $right = (float) $expected;

        return match ( $operator ) {
            '>'     => $left > $right,
            '<'     => $left < $right,
            '>='    => $left >= $right,
            '<='    => $left <= $right,
            default => false,
        };
    }

    /**
     * @return array<int, string>
     */
    protected function toList( mixed $expected ): array
    {
        if ( is_array( $expected ) ) {
            return array_map( fn ( $item ): string => $this->normalize( $item ), $expected );
        }

        if ( '' === $this->normalize( $expected ) ) {
            return [];
        }

        return array_map( 'trim', explode( ',', $this->normalize( $expected ) ) );
    }

    protected function inList( mixed $actual, mixed $expected ): bool
    {
        $list = $this->toList( $expected );

        if ( [] === $list ) {
            return false;
        }

        return in_array( $this->normalize( $actual ), $list, true );
    }

    protected function isChecked( mixed $value ): bool
    {
        if ( is_bool( $value ) ) {
            return $value;
        }

        if ( is_string( $value ) ) {
            return in_array( strtolower( trim( $value ) ), [ 'true', '1', 'yes', 'on', 'checked' ], true );
        }

        if ( is_int( $value ) ) {
            return 0 !== $value;
        }

        return false;
    }

    protected function includes( mixed $actual, mixed $expected ): bool
    {
        if ( ! is_array( $actual ) ) {
            return false;
        }

        $needle = $this->normalize( $expected );

        if ( '' === $needle ) {
            return false;
        }

        foreach ( $actual as $item ) {
            if ( $this->normalize( $item ) === $needle ) {
                return true;
            }
        }

        return false;
    }
}
