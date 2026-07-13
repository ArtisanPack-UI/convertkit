<?php

declare( strict_types=1 );

namespace Tests\Stubs;

/**
 * Simple stand-in for the `FormSubmission` model shipped by
 * `artisanpack-ui/forms`. The listener + mapper duck-type on
 * `->id`, `->form_id`, and `->values`.
 */
class FormSubmissionStub
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(
        public int $id,
        public int $form_id,
        public array $values,
    ) {
    }
}
