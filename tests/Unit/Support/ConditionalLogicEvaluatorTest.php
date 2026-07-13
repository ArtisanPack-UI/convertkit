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

it( 'returns false for an unknown operator', function (): void {
    $rules = [
        'conditions' => [
            [ 'field' => 'x', 'operator' => 'starts_with', 'value' => 'a' ],
        ],
    ];

    expect( $this->evaluator->evaluate( $rules, [ 'x' => 'apple' ] ) )->toBeFalse();
} );
