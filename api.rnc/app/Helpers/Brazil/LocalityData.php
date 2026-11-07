<?php

namespace App\Helpers\Brazil;

use Spatie\LaravelData\Data;

/**
 * A place at city level — the shape of `localityColumns()`, field for field. `region` is free text
 * inside the city ("Zona Leste"), never a list the product maintains.
 */
class LocalityData extends Data
{
    public function __construct(
        public string $state,
        public string $city,
        public ?string $cep = null,
        public ?string $region = null,
        public ?float $lat = null,
        public ?float $lng = null,
    ) {}
}
