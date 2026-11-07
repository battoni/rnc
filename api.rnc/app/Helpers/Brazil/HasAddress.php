<?php

namespace App\Helpers\Brazil;

/**
 * Reads a model's address columns as the matching DTO. A model whose table calls
 * `addressColumns()` uses `addressData()`; one whose table calls `localityColumns()` uses
 * `localityData()`. Calling the other one reads columns the table does not have.
 *
 * MySQL hands a `decimal` back as a numeric string, and the DTO's `?float` accepts it only because
 * this file is not `strict_types` — adding that declaration here means casting first. The suite
 * runs on SQLite, which answers a float, so it cannot see this (Helpers `RULES.md`, Known Gaps).
 *
 * No bootstrap model uses it yet — a project opts in — so PHPStan would report it as unused and
 * skip it; the ignore on the declaration says that on purpose. Its unit test covers the code.
 *
 * The columns are declared here, once, because that is where the requirement lives: a model that
 * uses this trait has the block its table called. Without them, static analysis reads every one of
 * these as an undefined property ON THE MODEL, and the report lands where the trait is used rather
 * than where it is written — 75 errors across five models in chapaja, all of them this.
 *
 * `addressColumns()` writes:
 *
 * @property string|null $cep
 * @property string $street
 * @property string $number
 * @property string|null $complement
 * @property string $district
 *
 * `localityColumns()` writes `region`; both write the rest:
 * @property string|null $region
 * @property string $city
 * @property string $state
 * @property float|string|null $lat
 * @property float|string|null $lng
 */
trait HasAddress // @phpstan-ignore trait.unused
{
    public function addressData(): AddressData
    {
        return new AddressData(
            street: $this->street,
            number: $this->number,
            district: $this->district,
            city: $this->city,
            state: $this->state,
            cep: $this->cep,
            complement: $this->complement,
            lat: $this->lat,
            lng: $this->lng,
        );
    }

    public function localityData(): LocalityData
    {
        return new LocalityData(
            state: $this->state,
            city: $this->city,
            cep: $this->cep,
            region: $this->region,
            lat: $this->lat,
            lng: $this->lng,
        );
    }
}
