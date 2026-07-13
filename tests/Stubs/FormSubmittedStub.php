<?php

declare( strict_types=1 );

namespace Tests\Stubs;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Stand-in for `\ArtisanPackUI\Forms\Events\FormSubmitted` — the listener
 * only reads `$event->submission`.
 */
class FormSubmittedStub
{
    use Dispatchable;

    public function __construct(
        public FormSubmissionStub $submission,
    ) {
    }
}
