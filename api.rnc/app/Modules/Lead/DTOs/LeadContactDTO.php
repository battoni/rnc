<?php

namespace App\Modules\Lead\DTOs;

use Spatie\LaravelData\Data;

/**
 * The contact given right after step 1 (rnc-1 R11): the company, one way to reach the person
 * (phone already normalised to `55…`, e-mail lower-cased) and the answer to the consent checkbox.
 */
class LeadContactDTO extends Data
{
    public function __construct(
        public string $company,
        public bool $consent,
        public ?string $name = null,
        public ?string $phone = null,
        public ?string $email = null,
    ) {}
}
