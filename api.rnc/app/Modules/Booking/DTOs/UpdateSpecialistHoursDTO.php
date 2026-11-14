<?php

namespace App\Modules\Booking\DTOs;

use Spatie\LaravelData\Attributes\Validation\Present;
use Spatie\LaravelData\Data;

class UpdateSpecialistHoursDTO extends Data
{
    public function __construct(
        /**
         * An empty list is a closed week: `Present`, not the `required` the package infers for an array.
         *
         * @var list<array{weekday: int, opens_at: string, closes_at: string}>
         */
        #[Present]
        public array $hours,
    ) {}
}
