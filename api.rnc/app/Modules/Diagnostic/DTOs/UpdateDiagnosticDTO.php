<?php

namespace App\Modules\Diagnostic\DTOs;

use App\Modules\Lead\DTOs\LeadContactDTO;
use Spatie\LaravelData\Data;

/**
 * A later step. `profile` holds only the answers the request sent — an absent answer is kept, an
 * explicit null clears it.
 */
class UpdateDiagnosticDTO extends Data
{
    /** @param  array<string, mixed>|null  $profile */
    public function __construct(
        public ?LeadContactDTO $contact = null,
        public ?array $profile = null,
        public ?string $step = null,
    ) {}
}
