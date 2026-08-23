<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Support\ConditionalLogicEvaluator;

beforeEach( function (): void {
    $this->evaluator = new ConditionalLogicEvaluator();
} );

it( 'always fires when the rule set is null', function (): void {
    expect( $this->evaluator->evaluate( null, [ 'email' => 'a@b.co' ] ) )->toBeTrue();
} );

it( 'always fires when the rule set is empty', function (): void {
    expect( $this->evaluator->evaluate( [], [ 'email' => 'a@b.co' ] ) )->toBeTrue();
} );

it( 'always fires when conditions list is empty', function (): void {
    $rules = [ 'match' => 'all', 'conditions' => [] ];

    expect( $this->evaluator->evaluate( $rules, [] ) )->toBeTrue();
} );

it( 'evaluates equals', function (): void {
    $rules = [
        'match'      => 'all',
        'conditions' => [
            [ 'field' => 'country', 'operator' => 'equals', 'value' => 'US' ],
        ],
    ];

    expect( $this->evaluator->evaluate( $rules, [ 'country' => 'US' ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $rules, [ 'country' => 'CA' ] ) )->toBeFalse();
} );

it( 'evaluates not_equals', function (): void {
    $rules = [
        'conditions' => [
            [ 'field' => 'country', 'operator' => 'not_equals', 'value' => 'US' ],
        ],
    ];

    expect( $this->evaluator->evaluate( $rules, [ 'country' => 'CA' ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $rules, [ 'country' => 'US' ] ) )->toBeFalse();
} );

it( 'evaluates contains against strings', function (): void {
    $rules = [
        'conditions' => [
            [ 'field' => 'comment', 'operator' => 'contains', 'value' => 'newsletter' ],
        ],
    ];

    expect( $this->evaluator->evaluate( $rules, [ 'comment' => 'I want the newsletter, please' ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $rules, [ 'comment' => 'no thanks' ] ) )->toBeFalse();
} );

it( 'evaluates contains against arrays', function (): void {
    $rules = [
        'conditions' => [
            [ 'field' => 'interests', 'operator' => 'contains', 'value' => 'php' ],
        ],
    ];

    expect( $this->evaluator->evaluate( $rules, [ 'interests' => [ 'php', 'laravel' ] ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $rules, [ 'interests' => [ 'go', 'rust' ] ] ) )->toBeFalse();
} );

it( 'evaluates not_contains', function (): void {
    $rules = [
        'conditions' => [
            [ 'field' => 'tags', 'operator' => 'not_contains', 'value' => 'spam' ],
        ],
    ];

    expect( $this->evaluator->evaluate( $rules, [ 'tags' => [ 'promo' ] ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $rules, [ 'tags' => [ 'spam' ] ] ) )->toBeFalse();
} );

it( 'evaluates is_empty and is_not_empty', function (): void {
    $empty = [
        'conditions' => [ [ 'field' => 'phone', 'operator' => 'is_empty' ] ],
    ];
    $notEmpty = [
        'conditions' => [ [ 'field' => 'phone', 'operator' => 'is_not_empty' ] ],
    ];

    expect( $this->evaluator->evaluate( $empty, [] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $empty, [ 'phone' => '' ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $empty, [ 'phone' => '   ' ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $empty, [ 'phone' => '555' ] ) )->toBeFalse();

    expect( $this->evaluator->evaluate( $notEmpty, [ 'phone' => '555' ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $notEmpty, [] ) )->toBeFalse();
} );

it( 'requires every condition when match is all', function (): void {
    $rules = [
        'match'      => 'all',
        'conditions' => [
            [ 'field' => 'country', 'operator' => 'equals', 'value' => 'US' ],
            [ 'field' => 'newsletter', 'operator' => 'equals', 'value' => 'yes' ],
        ],
    ];

    expect( $this->evaluator->evaluate( $rules, [ 'country' => 'US', 'newsletter' => 'yes' ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $rules, [ 'country' => 'US', 'newsletter' => 'no' ] ) )->toBeFalse();
} );

it( 'requires only one condition when match is any', function (): void {
    $rules = [
        'match'      => 'any',
        'conditions' => [
            [ 'field' => 'country', 'operator' => 'equals', 'value' => 'US' ],
            [ 'field' => 'newsletter', 'operator' => 'equals', 'value' => 'yes' ],
        ],
    ];

    expect( $this->evaluator->evaluate( $rules, [ 'country' => 'CA', 'newsletter' => 'yes' ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $rules, [ 'country' => 'CA', 'newsletter' => 'no' ] ) )->toBeFalse();
} );

it( 'treats a missing submission field as null for equality', function (): void {
    $rules = [
        'conditions' => [
            [ 'field' => 'ghost', 'operator' => 'equals', 'value' => '' ],
        ],
    ];

    expect( $this->evaluator->evaluate( $rules, [] ) )->toBeTrue();
} );

it( 'normalizes mixed scalar types for comparison', function (): void {
    $rules = [
        'conditions' => [
            [ 'field' => 'age', 'operator' => 'equals', 'value' => '30' ],
        ],
    ];

    expect( $this->evaluator->evaluate( $rules, [ 'age' => 30 ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $rules, [ 'age' => '30' ] ) )->toBeTrue();
} );

it( 'evaluates starts_with', function (): void {
    $rules = [
        'conditions' => [
            [ 'field' => 'email', 'operator' => 'starts_with', 'value' => 'jane' ],
        ],
    ];

    expect( $this->evaluator->evaluate( $rules, [ 'email' => 'jane@b.co' ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $rules, [ 'email' => 'john@b.co' ] ) )->toBeFalse();
    expect( $this->evaluator->evaluate( $rules, [] ) )->toBeFalse();
} );

it( 'evaluates ends_with', function (): void {
    $rules = [
        'conditions' => [
            [ 'field' => 'email', 'operator' => 'ends_with', 'value' => '@company.com' ],
        ],
    ];

    expect( $this->evaluator->evaluate( $rules, [ 'email' => 'boss@company.com' ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $rules, [ 'email' => 'boss@other.com' ] ) )->toBeFalse();
} );

it( 'evaluates numeric comparisons', function (): void {
    $greater      = [ 'conditions' => [ [ 'field' => 'age', 'operator' => 'greater_than', 'value' => 18 ] ] ];
    $less         = [ 'conditions' => [ [ 'field' => 'age', 'operator' => 'less_than', 'value' => 18 ] ] ];
    $greaterEqual = [ 'conditions' => [ [ 'field' => 'age', 'operator' => 'greater_or_equal', 'value' => 18 ] ] ];
    $lessEqual    = [ 'conditions' => [ [ 'field' => 'age', 'operator' => 'less_or_equal', 'value' => 18 ] ] ];

    expect( $this->evaluator->evaluate( $greater, [ 'age' => 21 ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $greater, [ 'age' => 18 ] ) )->toBeFalse();
    expect( $this->evaluator->evaluate( $less, [ 'age' => 16 ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $less, [ 'age' => 18 ] ) )->toBeFalse();

    expect( $this->evaluator->evaluate( $greaterEqual, [ 'age' => 18 ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $greaterEqual, [ 'age' => 17 ] ) )->toBeFalse();
    expect( $this->evaluator->evaluate( $lessEqual, [ 'age' => 18 ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $lessEqual, [ 'age' => 19 ] ) )->toBeFalse();
} );

it( 'treats non-numeric values as failing numeric comparisons', function (): void {
    $rules = [ 'conditions' => [ [ 'field' => 'age', 'operator' => 'greater_than', 'value' => 18 ] ] ];

    expect( $this->evaluator->evaluate( $rules, [ 'age' => 'twenty' ] ) )->toBeFalse();
    expect( $this->evaluator->evaluate( $rules, [] ) )->toBeFalse();
} );

it( 'preserves integer precision past the float mantissa in numeric comparisons', function (): void {
    // 9007199254740992 is 2^53 — the largest integer a float represents
    // exactly. Casting either operand to float would collapse these two
    // consecutive integers to the same value and report them equal.
    $greater = [ 'conditions' => [ [ 'field' => 'n', 'operator' => 'greater_than', 'value' => '9007199254740992' ] ] ];
    $less    = [ 'conditions' => [ [ 'field' => 'n', 'operator' => 'less_than', 'value' => '9007199254740993' ] ] ];

    expect( $this->evaluator->evaluate( $greater, [ 'n' => '9007199254740993' ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $less, [ 'n' => '9007199254740992' ] ) )->toBeTrue();

    // The boundary itself is not strictly greater/less than its equal.
    expect( $this->evaluator->evaluate( $greater, [ 'n' => '9007199254740992' ] ) )->toBeFalse();
} );

it( 'evaluates in against a comma-separated list', function (): void {
    $rules = [
        'conditions' => [
            [ 'field' => 'country', 'operator' => 'in', 'value' => 'US, CA, MX' ],
        ],
    ];

    expect( $this->evaluator->evaluate( $rules, [ 'country' => 'CA' ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $rules, [ 'country' => 'FR' ] ) )->toBeFalse();
} );

it( 'evaluates in against an array of values', function (): void {
    $rules = [
        'conditions' => [
            [ 'field' => 'country', 'operator' => 'in', 'value' => [ 'US', 'CA' ] ],
        ],
    ];

    expect( $this->evaluator->evaluate( $rules, [ 'country' => 'US' ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $rules, [ 'country' => 'FR' ] ) )->toBeFalse();
} );

it( 'evaluates not_in', function (): void {
    $rules = [
        'conditions' => [
            [ 'field' => 'country', 'operator' => 'not_in', 'value' => 'US, CA' ],
        ],
    ];

    expect( $this->evaluator->evaluate( $rules, [ 'country' => 'FR' ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $rules, [ 'country' => 'US' ] ) )->toBeFalse();
} );

it( 'fires only when the consent box is checked', function (): void {
    $rules = [
        'conditions' => [
            [ 'field' => 'consent', 'operator' => 'checked' ],
        ],
    ];

    expect( $this->evaluator->evaluate( $rules, [ 'consent' => true ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $rules, [ 'consent' => '1' ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $rules, [ 'consent' => 'yes' ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $rules, [ 'consent' => 'on' ] ) )->toBeTrue();

    expect( $this->evaluator->evaluate( $rules, [ 'consent' => false ] ) )->toBeFalse();
    expect( $this->evaluator->evaluate( $rules, [ 'consent' => '0' ] ) )->toBeFalse();
    expect( $this->evaluator->evaluate( $rules, [ 'consent' => '' ] ) )->toBeFalse();
    expect( $this->evaluator->evaluate( $rules, [] ) )->toBeFalse();
} );

it( 'evaluates unchecked as the inverse of checked', function (): void {
    $rules = [
        'conditions' => [
            [ 'field' => 'marketing_opt_out', 'operator' => 'unchecked' ],
        ],
    ];

    expect( $this->evaluator->evaluate( $rules, [ 'marketing_opt_out' => false ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $rules, [] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $rules, [ 'marketing_opt_out' => true ] ) )->toBeFalse();
} );

it( 'evaluates includes and not_includes against arrays', function (): void {
    $includes    = [ 'conditions' => [ [ 'field' => 'interests', 'operator' => 'includes', 'value' => 'php' ] ] ];
    $notIncludes = [ 'conditions' => [ [ 'field' => 'interests', 'operator' => 'not_includes', 'value' => 'spam' ] ] ];

    expect( $this->evaluator->evaluate( $includes, [ 'interests' => [ 'php', 'laravel' ] ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $includes, [ 'interests' => [ 'go', 'rust' ] ] ) )->toBeFalse();
    expect( $this->evaluator->evaluate( $includes, [ 'interests' => 'php' ] ) )->toBeFalse();

    expect( $this->evaluator->evaluate( $notIncludes, [ 'interests' => [ 'php' ] ] ) )->toBeTrue();
    expect( $this->evaluator->evaluate( $notIncludes, [ 'interests' => [ 'spam' ] ] ) )->toBeFalse();
} );

it( 'returns false for an unknown operator', function (): void {
    $rules = [
        'conditions' => [
            [ 'field' => 'x', 'operator' => 'regex_match', 'value' => 'a' ],
        ],
    ];

    expect( $this->evaluator->evaluate( $rules, [ 'x' => 'apple' ] ) )->toBeFalse();
} );
