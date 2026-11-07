<?php

namespace App\Helpers\Brazil;

use Spatie\LaravelData\Data;

/**
 * A full street address — the shape of `addressColumns()`, field for field. A resource spreads it
 * (`...$this->addressData()->toArray()`) instead of listing the columns again.
 */
class AddressData extends Data
{
    public function __construct(
        public string $street,
        public string $number,
        public string $district,
        public string $city,
        public string $state,
        public ?string $cep = null,
        public ?string $complement = null,
        public ?float $lat = null,
        public ?float $lng = null,
    ) {}
}
