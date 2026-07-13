<?php

declare( strict_types=1 );

use ArtisanPackUI\ConvertKit\Support\FieldMapper;
use ArtisanPackUI\ConvertKit\Support\FieldMapperException;
use Tests\Stubs\FormSubmissionStub;

beforeEach( function (): void {
    $this->mapper = new FieldMapper();
} );

it( 'maps reserved keys and packs custom fields', function (): void {
    $submission = new FormSubmissionStub( 1, 10, [
        'email'   => 'a@b.co',
        'name'    => 'Ada',
        'company' => 'Acme',
    ] );

    $fieldMap = [
        'email_address' => 'email',
        'first_name'    => 'name',
        'company'       => 'company',
    ];

    $payload = $this->mapper->map( $submission, $fieldMap );

    expect( $payload )->toBe( [
        'email_address' => 'a@b.co',
        'first_name'    => 'Ada',
        'fields'        => [ 'company' => 'Acme' ],
    ] );
} );

it( 'throws when the field map has no email_address entry', function (): void {
    $submission = new FormSubmissionStub( 1, 10, [ 'email' => 'a@b.co' ] );

    $this->mapper->map( $submission, [ 'first_name' => 'name' ] );
} )->throws( FieldMapperException::class, 'email_address' );

it( 'throws when the mapped email value is empty', function (): void {
    $submission = new FormSubmissionStub( 1, 10, [ 'email' => '   ' ] );

    $this->mapper->map( $submission, [ 'email_address' => 'email' ] );
} )->throws( FieldMapperException::class );

it( 'throws when the mapped email slug is missing from the submission', function (): void {
    $submission = new FormSubmissionStub( 1, 10, [ 'name' => 'Ada' ] );

    $this->mapper->map( $submission, [ 'email_address' => 'email' ] );
} )->throws( FieldMapperException::class );

it( 'ignores unmapped submission values', function (): void {
    $submission = new FormSubmissionStub( 1, 10, [
        'email'  => 'a@b.co',
        'secret' => 'hidden',
    ] );

    $payload = $this->mapper->map( $submission, [ 'email_address' => 'email' ] );

    expect( $payload )->toBe( [ 'email_address' => 'a@b.co' ] );
} );

it( 'ignores field-map entries whose submission slug is not present', function (): void {
    $submission = new FormSubmissionStub( 1, 10, [ 'email' => 'a@b.co' ] );

    $payload = $this->mapper->map( $submission, [
        'email_address' => 'email',
        'company'       => 'company_slug_that_does_not_exist',
    ] );

    expect( $payload )->toBe( [ 'email_address' => 'a@b.co' ] );
} );

it( 'exposes a pure mapValues variant', function (): void {
    $payload = $this->mapper->mapValues(
        [ 'email' => 'a@b.co', 'name' => 'Ada' ],
        [ 'email_address' => 'email', 'first_name' => 'name' ],
    );

    expect( $payload )->toBe( [
        'email_address' => 'a@b.co',
        'first_name'    => 'Ada',
    ] );
} );
